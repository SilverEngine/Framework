<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Crypto\Crypter;
use Silver\Crypto\CryptoException;

/**
 * Thin wrapper over $_COOKIE / Response::setCookie.
 *
 * Values are JSON-encoded with a small envelope that records whether
 * the payload was encrypted on the way out. ::get() honours the flag
 * and refuses to silently treat malformed ciphertext as plaintext.
 */
final class Cookie
{
    private const ENVELOPE_PREFIX = 'sec:';
    private static string $name   = '';
    private static string $value  = '';
    private static int $expire    = 315360000; // 10 years

    public static function set(string $name, mixed $value, int $expire = 0, bool $extend = false): self
    {
        self::$name = $name;

        if ($expire > 0) {
            self::$expire = $expire;
        }

        $time = $extend ? self::$expire : time() + self::$expire;

        $payload = [
            'values'    => is_array($value) ? $value : [$value],
            'expire'    => $time,
            'encrypted' => false,
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        self::setCookie($name, $encoded, time() + self::$expire);
        self::$value = $encoded;

        return new self();
    }

    public function encrypt(): bool
    {
        if (self::$name === '' || self::$value === '') {
            return false;
        }
        $cipher = Crypter::encrypt(self::$value);
        $wire   = self::ENVELOPE_PREFIX . $cipher;
        self::setCookie(self::$name, $wire, time() + self::$expire);
        return true;
    }

    public static function get(string $name, ?string $returnType = null): mixed
    {
        if (!self::exists($name)) {
            return null;
        }

        self::$name = $name;
        $raw        = (string) $_COOKIE[$name];

        $decoded = self::decodePayload($raw);
        if ($decoded === null) {
            return null;
        }

        return match ($returnType) {
            'expire' => $decoded['expire'] ?? null,
            'all'    => $decoded,
            default  => $decoded['values'] ?? null,
        };
    }

    public static function isCrypted(string $name): bool
    {
        if (!self::exists($name)) {
            return false;
        }
        return str_starts_with((string) $_COOKIE[$name], self::ENVELOPE_PREFIX);
    }

    public static function attach(string $key, mixed $newValue): bool
    {
        $oldData = self::get($key, 'all');
        if (!is_array($oldData)) {
            return false;
        }

        $newValue = is_array($newValue) ? $newValue : [$newValue];
        $merged   = array_merge($oldData['values'] ?? [], $newValue);

        $self = self::set($key, $merged, $oldData['expire'] ?? 0, true);
        if (!empty($oldData['encrypted'])) {
            $self->encrypt();
        }
        return true;
    }

    public static function detach(string $key, string|int $valueToDelete): bool
    {
        $oldData = self::get($key, 'all');
        if (!is_array($oldData)) {
            return false;
        }

        $values = $oldData['values'] ?? [];
        unset($values[$valueToDelete]);

        $self = self::set($key, $values, $oldData['expire'] ?? 0, true);
        if (!empty($oldData['encrypted'])) {
            $self->encrypt();
        }
        return true;
    }

    public static function all(): array
    {
        return $_COOKIE;
    }

    public static function exists(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }

    public static function delete(string $key): void
    {
        self::set($key, '', -1);
    }

    public static function flush(): void
    {
        foreach (array_keys(self::all()) as $key) {
            self::delete($key);
        }
    }

    private static function decodePayload(string $raw): ?array
    {
        if (str_starts_with($raw, self::ENVELOPE_PREFIX)) {
            try {
                $json = Crypter::decrypt(substr($raw, strlen(self::ENVELOPE_PREFIX)));
            } catch (CryptoException) {
                return null;
            }
            $data = json_decode($json, true);
            if (is_array($data)) {
                $data['encrypted'] = true;
            }
            return is_array($data) ? $data : null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function setCookie(string $key, string $value, int $expiration): void
    {
        Response::instance()->setCookie($key, $value, $expiration);
    }
}
