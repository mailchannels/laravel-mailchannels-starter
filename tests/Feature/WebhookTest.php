<?php

namespace Tests\Feature;

use App\Webhooks\PublicKeyResolver;
use MailChannels\Client;
use MailChannelsStub\WebhookSigner;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    private function postWebhook(string $body, array $headers)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/webhooks/mailchannels', [], [], [], $server, $body);
    }

    public function test_valid_signed_webhook_is_accepted(): void
    {
        $body = WebhookSigner::sampleBody();
        $this->postWebhook($body, WebhookSigner::headers($body))->assertOk()->assertJson(['received' => 2]);
    }

    public function test_public_key_is_fetched_from_api_and_cached(): void
    {
        $body = WebhookSigner::sampleBody();
        $this->postWebhook($body, WebhookSigner::headers($body))->assertOk();

        // Make further fetches fail: the cached key is still used.
        $this->app->instance(Client::class, new Client(apiKey: 'x', baseUrl: 'http://127.0.0.1:1/tx/v1'));
        $this->postWebhook($body, WebhookSigner::headers($body))->assertOk();
    }

    public function test_env_public_key_override_is_used(): void
    {
        $other = sodium_crypto_sign_keypair();
        config(['services.mailchannels.webhook_public_key' => base64_encode(sodium_crypto_sign_publickey($other))]);
        $this->app->forgetInstance(PublicKeyResolver::class);
        $body = WebhookSigner::sampleBody();

        $this->postWebhook($body, WebhookSigner::headers($body, secretKey: sodium_crypto_sign_secretkey($other)))->assertOk();
        $this->postWebhook($body, WebhookSigner::headers($body))->assertUnauthorized();
    }

    public function test_tampered_body_is_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        $headers = WebhookSigner::headers($body);
        $this->postWebhook(str_replace('delivered', 'hard-bounced', $body), $headers)->assertUnauthorized();
    }

    public function test_tampered_body_with_recomputed_digest_is_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        $headers = WebhookSigner::headers($body);
        $tampered = str_replace('delivered', 'hard-bounced', $body);
        $headers['Content-Digest'] = 'sha-256=:'.base64_encode(hash('sha256', $tampered, true)).':';
        $this->postWebhook($tampered, $headers)->assertUnauthorized();
    }

    public function test_bad_signature_is_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        $wrongKey = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $this->postWebhook($body, WebhookSigner::headers($body, secretKey: $wrongKey))->assertUnauthorized();
    }

    public function test_stale_and_future_signatures_are_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        $this->postWebhook($body, WebhookSigner::headers($body, created: time() - 301))->assertUnauthorized();
        $this->postWebhook($body, WebhookSigner::headers($body, created: time() + 61))->assertUnauthorized();
        $this->postWebhook($body, WebhookSigner::headers($body, created: time() - 290))->assertOk();
    }

    public function test_missing_signature_headers_are_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        foreach (array_keys(WebhookSigner::headers($body)) as $drop) {
            $headers = WebhookSigner::headers($body);
            unset($headers[$drop]);
            $this->postWebhook($body, $headers)->assertUnauthorized();
        }
    }

    public function test_wrong_algorithm_is_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        $headers = WebhookSigner::headers($body);
        $headers['Signature-Input'] = str_replace('alg="ed25519"', 'alg="hmac-sha256"', $headers['Signature-Input']);
        $this->postWebhook($body, $headers)->assertUnauthorized();
    }

    public function test_unknown_key_id_is_rejected(): void
    {
        $body = WebhookSigner::sampleBody();
        $this->postWebhook($body, WebhookSigner::headers($body, keyId: 'otherkey'))->assertUnauthorized();
    }

    public function test_signed_invalid_json_is_bad_request(): void
    {
        foreach (['not json', '{"event":"delivered"}', '[{"event":"delivered"}]'] as $body) {
            $this->postWebhook($body, WebhookSigner::headers($body))->assertStatus(400);
        }
    }
}
