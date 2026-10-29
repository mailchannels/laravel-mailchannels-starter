<?php

declare(strict_types=1);

namespace MailChannelsStub;

/**
 * TEST-ONLY Ed25519 keypair for the local stub Email API.
 *
 * This key is public (it is committed to the repository), so anyone can sign
 * webhooks with it. Never configure it as a trusted key outside local
 * development and tests. Production apps verify webhooks against the key
 * served by https://api.mailchannels.net/tx/v1/webhook/public-key.
 */
final class TestKeys
{
    public const KEY_ID = 'mckey';

    /** TEST-ONLY 32-byte Ed25519 seed, base64-encoded. */
    public const SEED_BASE64 = 'peeRj5WdqR2OpGzBndnMSCLY254kpC6EG8pYiDuXPlU=';

    /** DER prefix of an Ed25519 SubjectPublicKeyInfo (RFC 8410). */
    private const SPKI_PREFIX_HEX = '302a300506032b6570032100';

    public static function secretKey(): string
    {
        return sodium_crypto_sign_secretkey(
            sodium_crypto_sign_seed_keypair(base64_decode(self::SEED_BASE64, true)),
        );
    }

    /** Raw 32-byte public key. */
    public static function publicKey(): string
    {
        return sodium_crypto_sign_publickey_from_secretkey(self::secretKey());
    }

    /** Base64 SPKI DER, the format served by GET /webhook/public-key. */
    public static function publicKeySpkiBase64(): string
    {
        return base64_encode(hex2bin(self::SPKI_PREFIX_HEX).self::publicKey());
    }
}
