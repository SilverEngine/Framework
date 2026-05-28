<?php
declare(strict_types=1);

namespace Silver\Crypto;

/**
 * Symmetric crypto + HMAC signing.
 *
 * encrypt/decrypt → AES-256-GCM. Wire format (base64):
 *   [12-byte nonce] [16-byte tag] [ciphertext…]
 *
 * sign/verify → HMAC-SHA256 with constant-time compare. The base64
 * here uses the URL-safe alphabet so signatures can ride in query
 * strings without further encoding.
 *
 * Key derivation: SHA-256 of the configured key string, binary. This
 * matches the historical EncryptedCast format so existing ciphertexts
 * keep decrypting after the cut-over.
 */
final class Crypter
{
    public static function encrypt(string $plain, ?string $key = null): string
    {
        $derived = self::deriveKey($key);
        $nonce   = random_bytes(12);
        $tag     = '';
        $ct      = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $derived,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );
        if ($ct === false) {
            throw new CryptoException('AES-256-GCM encryption failed.');
        }
        return base64_encode($nonce . $tag . $ct);
    }

    public static function decrypt(string $payload, ?string $key = null): string
    {
        $blob = base64_decode($payload, strict: true);
        if ($blob === false || strlen($blob) < 28) {
            throw new CryptoException('Malformed ciphertext.');
        }
        $derived    = self::deriveKey($key);
        $nonce      = substr($blob, 0, 12);
        $tag        = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);
        $plain      = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $derived,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );
        if ($plain === false) {
            throw new CryptoException('Decryption failed (bad key or tampered ciphertext).');
        }
        return $plain;
    }

    public static function sign(string $message, ?string $key = null): string
    {
        $derived = self::deriveKey($key);
        $mac     = hash_hmac('sha256', $message, $derived, binary: true);
        return self::base64UrlEncode($mac);
    }

    public static function verify(string $message, string $signature, ?string $key = null): bool
    {
        $expected = self::sign($message, $key);
        return hash_equals($expected, $signature);
    }

    private static function deriveKey(?string $key): string
    {
        $raw = $key ?? self::appKey();
        if ($raw === '') {
            throw new CryptoException(
                'APP_KEY is not set. Run "php silver key:generate" to create one.',
            );
        }
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), strict: true);
            if ($decoded !== false && $decoded !== '') {
                return hash('sha256', $decoded, binary: true);
            }
        }
        return hash('sha256', $raw, binary: true);
    }

    private static function appKey(): string
    {
        return (string) (function_exists('env') ? env('APP_KEY', '') : '');
    }

    private static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}
