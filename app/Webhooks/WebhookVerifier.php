<?php

namespace App\Webhooks;

use Illuminate\Http\Request;
use InvalidArgumentException;
use MailChannels\Webhook\Verifier;
use SodiumException;

/**
 * Verifies MailChannels' RFC 9421 webhook signatures.
 *
 * Parsing, the Content-Digest check and the signature base come from the
 * SDK's MailChannels\Webhook\Verifier. The SDK leaves the Ed25519 check to
 * the caller, so it is done here with libsodium (bundled with PHP).
 *
 * @see https://docs.mailchannels.com/email-api/webhooks#verifying-message-signatures
 */
class WebhookVerifier
{
    public function __construct(
        private readonly PublicKeyResolver $keys,
        private readonly int $maxAgeSeconds = 300,
        private readonly int $maxFutureSeconds = 60,
    ) {}

    /**
     * @throws WebhookVerificationException when the request is not authentic
     */
    public function verify(Request $request, ?int $now = null): void
    {
        $now ??= time();
        $body = $request->getContent();
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            if (isset($values[0]) && is_string($values[0])) {
                $headers[strtolower($name)] = $values[0];
            }
        }

        foreach (['content-digest', 'signature-input', 'signature'] as $required) {
            if (($headers[$required] ?? '') === '') {
                throw new WebhookVerificationException("Missing {$required} header.");
            }
        }

        if (! Verifier::verifyContentDigest($headers, $body)) {
            throw new WebhookVerificationException('Content-Digest does not match the body.');
        }

        try {
            $params = Verifier::parseSignatureInput($headers['signature-input']);
        } catch (InvalidArgumentException) {
            throw new WebhookVerificationException('Malformed Signature-Input header.');
        }

        // The digest only protects the body if the signature covers it.
        if (! in_array('content-digest', $params->coveredComponents, true)) {
            throw new WebhookVerificationException('Signature does not cover content-digest.');
        }
        if ($params->algorithm !== 'ed25519') {
            throw new WebhookVerificationException('Unsupported signature algorithm.');
        }
        if ($params->created === null
            || $now - $params->created > $this->maxAgeSeconds
            || $params->created - $now > $this->maxFutureSeconds) {
            throw new WebhookVerificationException('Signature is stale or from the future.');
        }
        if ($params->keyId === null || $params->keyId === '') {
            throw new WebhookVerificationException('Signature-Input has no keyid.');
        }

        $signature = self::signatureBytes($headers['signature'], $params->signatureName);
        if ($signature === null) {
            throw new WebhookVerificationException('Malformed Signature header.');
        }

        try {
            $base = Verifier::buildSignatureBase('POST', $request->fullUrl(), $headers, $body, $params);
        } catch (InvalidArgumentException) {
            throw new WebhookVerificationException('Cannot build the signature base.');
        }

        $publicKey = $this->keys->forKeyId($params->keyId);

        try {
            $valid = sodium_crypto_sign_verify_detached($signature, $base, $publicKey);
        } catch (SodiumException) {
            $valid = false;
        }
        if (! $valid) {
            throw new WebhookVerificationException('Invalid signature.');
        }
    }

    /** Extract `<name>=:<base64>:` from the Signature header (RFC 9421 §4.2). */
    private static function signatureBytes(string $header, string $name): ?string
    {
        $pattern = '/(?:^|,)\s*'.preg_quote($name, '/').'=:([A-Za-z0-9+\/]+={0,2}):/';
        if (preg_match($pattern, $header, $m) !== 1) {
            return null;
        }
        $bytes = base64_decode($m[1], true);

        return $bytes !== false && strlen($bytes) === SODIUM_CRYPTO_SIGN_BYTES ? $bytes : null;
    }
}
