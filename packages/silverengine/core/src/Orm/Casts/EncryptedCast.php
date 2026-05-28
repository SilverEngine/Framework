<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

/**
 * AES-256-GCM with the app key as the master. Format
 * (base64-encoded): [12-byte nonce][16-byte tag][ciphertext…].
 * Out of scope: rotation, envelope encryption, key-versioning.
 */
final class EncryptedCast implements CastsAttribute
{
    private readonly string $key;

    public function __construct(?string $key = null)
    {
        $raw = $key ?? (string) (function_exists('env') ? env('APP_KEY', '') : '');
        if ($raw === '') {
            throw new \RuntimeException('EncryptedCast requires APP_KEY (or an explicit key).');
        }
        $this->key = hash('sha256', $raw, binary: true);
    }

    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') return null;
        $blob = base64_decode((string) $value, strict: true);
        if ($blob === false || strlen($blob) < 28) {
            throw new \RuntimeException('EncryptedCast: malformed ciphertext.');
        }
        $nonce      = substr($blob, 0, 12);
        $tag        = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);
        $plain      = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain === false) {
            throw new \RuntimeException('EncryptedCast: decryption failed.');
        }
        return $plain;
    }

    public function set(mixed $value): mixed
    {
        if ($value === null) return null;
        $nonce = random_bytes(12);
        $tag   = '';
        $ct    = openssl_encrypt((string) $value, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ct === false) {
            throw new \RuntimeException('EncryptedCast: encryption failed.');
        }
        return base64_encode($nonce . $tag . $ct);
    }
}
