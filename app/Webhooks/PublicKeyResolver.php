<?php

namespace App\Webhooks;

use Illuminate\Contracts\Cache\Repository as Cache;
use MailChannels\Client;
use MailChannels\Exception\MailChannelsException;
use MailChannels\Exception\NotFoundException;
use Throwable;

/**
 * Resolves the Ed25519 public key that signed a webhook.
 *
 * MAILCHANNELS_WEBHOOK_PUBLIC_KEY, when set, overrides the lookup. Otherwise
 * the key is fetched from {MAILCHANNELS_API_BASE_URL}/webhook/public-key?id=
 * through the SDK and cached.
 */
class PublicKeyResolver
{
    /** DER prefix of an Ed25519 SubjectPublicKeyInfo (RFC 8410). */
    private const SPKI_PREFIX = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

    public function __construct(
        private readonly Client $client,
        private readonly Cache $cache,
        private readonly ?string $override,
        private readonly int $cacheSeconds,
    ) {}

    /** @return string raw 32-byte public key */
    public function forKeyId(string $keyId): string
    {
        if ($this->override !== null && trim($this->override) !== '') {
            return self::parse($this->override)
                ?? throw new WebhookVerificationException('MAILCHANNELS_WEBHOOK_PUBLIC_KEY is not a valid Ed25519 key.', temporary: true);
        }

        // Key ids are short tokens; refusing anything else keeps attacker input out of URLs and cache keys.
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) !== 1) {
            throw new WebhookVerificationException('Invalid keyid.');
        }

        $cacheKey = 'mailchannels:webhook-public-key:'.$keyId;
        $cached = $this->cache->get($cacheKey);
        if (is_string($cached) && ($key = self::parse($cached)) !== null) {
            return $key;
        }

        try {
            $response = $this->client->webhooks->publicKey($keyId);
        } catch (NotFoundException) {
            throw new WebhookVerificationException("Unknown webhook public key '{$keyId}'.");
        } catch (MailChannelsException|Throwable) {
            throw new WebhookVerificationException("Could not fetch webhook public key '{$keyId}'.", temporary: true);
        }

        $key = is_string($response->key) ? self::parse($response->key) : null;
        if ($key === null) {
            throw new WebhookVerificationException("Webhook public key '{$keyId}' is not a valid Ed25519 key.", temporary: true);
        }

        // Stored base64-encoded so any cache store (database, file, redis) can hold it.
        $this->cache->put($cacheKey, base64_encode($key), $this->cacheSeconds);

        return $key;
    }

    /**
     * Accepts PEM, base64 SPKI DER (what the API serves) or a base64 raw key.
     */
    public static function parse(string $value): ?string
    {
        $value = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $value) ?? '';
        $der = base64_decode($value, true);
        if ($der === false) {
            return null;
        }
        if (strlen($der) === 44 && str_starts_with($der, self::SPKI_PREFIX)) {
            return substr($der, 12);
        }

        return strlen($der) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $der : null;
    }
}
