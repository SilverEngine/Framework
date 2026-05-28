<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

use Silver\Crypto\Crypter;

/**
 * AES-256-GCM at-rest column encryption. Wire format identical to
 * {@see Crypter} — base64([12 nonce][16 tag][ciphertext]) — so payloads
 * are interchangeable with anything else the framework signs.
 */
final readonly class EncryptedCast implements CastsAttribute
{
    public function __construct(private ?string $key = null) {}

    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Crypter::decrypt((string) $value, $this->key);
    }

    public function set(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return Crypter::encrypt((string) $value, $this->key);
    }
}
