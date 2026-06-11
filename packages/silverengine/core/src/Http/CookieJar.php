<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Crypto\Crypter;
use Silver\Crypto\CryptoException;

/**
 * Cookie service over $_COOKIE / Response::setCookie.
 *
 * Values are JSON-encoded with a small envelope that records whether
 * the payload was encrypted on the way out. ::get() honours the flag
 * and refuses to silently treat malformed ciphertext as plaintext.
 *
 * Instance state ($name/$value/$expire) only carries the last set()
 * so the fluent `set(...)->encrypt()` chain works; it is not shared
 * cross-request meaning.
 */
final class CookieJar
{
    private const ENVELOPE_PREFIX = 'sec:';

    private string $name  = '';
    private string $value = '';
    private int $expire   = 315360000; // 10 years

    public function set(string $name, mixed $value, int $expire = 0, bool $extend = false): self
    {
        $this->name = $name;

        if ($expire > 0) {
            $this->expire = $expire;
        }

        $time = $extend ? $this->expire : time() + $this->expire;

        $payload = [
            'values'    => is_array($value) ? $value : [$value],
            'expire'    => $time,
            'encrypted' => false,
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->setCookie($name, $encoded, time() + $this->expire);
        $this->value = $encoded;

        return $this;
    }

    public function encrypt(): bool
    {
        if ($this->name === '' || $this->value === '') {
            return false;
        }
        $cipher = Crypter::encrypt($this->value);
        $wire   = self::ENVELOPE_PREFIX . $cipher;
        $this->setCookie($this->name, $wire, time() + $this->expire);
        return true;
    }

    public function get(string $name, ?string $returnType = null): mixed
    {
        if (!$this->exists($name)) {
            return null;
        }

        $this->name = $name;
        $raw        = (string) $_COOKIE[$name];

        $decoded = $this->decodePayload($raw);
        if ($decoded === null) {
            return null;
        }

        return match ($returnType) {
            'expire' => $decoded['expire'] ?? null,
            'all'    => $decoded,
            default  => $decoded['values'] ?? null,
        };
    }

    public function isCrypted(string $name): bool
    {
        if (!$this->exists($name)) {
            return false;
        }
        return str_starts_with((string) $_COOKIE[$name], self::ENVELOPE_PREFIX);
    }

    public function attach(string $key, mixed $newValue): bool
    {
        $oldData = $this->get($key, 'all');
        if (!is_array($oldData)) {
            return false;
        }

        $newValue = is_array($newValue) ? $newValue : [$newValue];
        $merged   = array_merge($oldData['values'] ?? [], $newValue);

        $this->set($key, $merged, $oldData['expire'] ?? 0, true);
        if (!empty($oldData['encrypted'])) {
            $this->encrypt();
        }
        return true;
    }

    public function detach(string $key, string|int $valueToDelete): bool
    {
        $oldData = $this->get($key, 'all');
        if (!is_array($oldData)) {
            return false;
        }

        $values = $oldData['values'] ?? [];
        unset($values[$valueToDelete]);

        $this->set($key, $values, $oldData['expire'] ?? 0, true);
        if (!empty($oldData['encrypted'])) {
            $this->encrypt();
        }
        return true;
    }

    public function all(): array
    {
        return $_COOKIE;
    }

    public function exists(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }

    public function delete(string $key): void
    {
        $this->set($key, '', -1);
    }

    public function flush(): void
    {
        foreach (array_keys($this->all()) as $key) {
            $this->delete($key);
        }
    }

    private function decodePayload(string $raw): ?array
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

    private function setCookie(string $key, string $value, int $expiration): void
    {
        Response::instance()->setCookie($key, $value, $expiration);
    }
}
