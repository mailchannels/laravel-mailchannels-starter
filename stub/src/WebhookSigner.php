<?php

declare(strict_types=1);

namespace MailChannelsStub;

/**
 * Signs a webhook body the way MailChannels does (RFC 9421, Ed25519 over the
 * Content-Digest header). Used by the stub CLI and by the app's tests.
 */
final class WebhookSigner
{
    /**
     * @return array{Content-Digest: string, Signature-Input: string, Signature: string}
     */
    public static function headers(
        string $body,
        ?int $created = null,
        ?string $secretKey = null,
        string $keyId = TestKeys::KEY_ID,
        ?string $name = null,
    ): array {
        $created ??= time();
        $name ??= 'sig_'.$created;
        $digest = 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':';
        $params = sprintf('("content-digest");created=%d;alg="ed25519";keyid="%s"', $created, $keyId);
        $signingString = '"content-digest": '.$digest."\n".'"@signature-params": '.$params;
        $signature = sodium_crypto_sign_detached($signingString, $secretKey ?? TestKeys::secretKey());

        return [
            'Content-Digest' => $digest,
            'Signature-Input' => $name.'='.$params,
            'Signature' => $name.'=:'.base64_encode($signature).':',
        ];
    }

    /** A synthetic batch of delivery events shaped like a real MailChannels webhook. */
    public static function sampleBody(string $customerHandle = 'example-customer'): string
    {
        $now = time();

        return json_encode([
            [
                'email' => 'sender@example.com',
                'customer_handle' => $customerHandle,
                'timestamp' => $now,
                'event' => 'processed',
                'request_id' => 'stub-request-1',
                'message_id' => 'stub-message-1',
                'recipients' => ['user@example.test'],
            ],
            [
                'email' => 'sender@example.com',
                'customer_handle' => $customerHandle,
                'timestamp' => $now,
                'event' => 'delivered',
                'request_id' => 'stub-request-1',
                'message_id' => 'stub-message-1',
                'recipients' => ['user@example.test'],
                'status' => '250',
            ],
        ], JSON_UNESCAPED_SLASHES);
    }
}
