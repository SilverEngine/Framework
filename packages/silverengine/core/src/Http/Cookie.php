<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Support\Crypter;

final class Cookie
{
    private static string $name = '';
    private static string $value = '';
    private static int $expire = 315360000; // 10 years

    public static function set(string $name, mixed $value, int $expire = 0, bool $extend = false): self
    {
        self::$name = $name;

        if ($expire > 0) {
            self::$expire = $expire;
        }

        $time = $extend ? self::$expire : time() + self::$expire;

        $data = [
            'values'    => is_array($value) ? $value : [$value],
            'expire'    => $time,
            'encrypted' => '0',
        ];

        self::setCookie($name, json_encode($data), time() + self::$expire);
        $data['encrypted'] = 1;
        self::$value = json_encode($data);

        return new self();
    }

    public function encrypt(): bool
    {
        if (isset($_COOKIE)) {
            $value = Crypter::crypt(self::$value);
            self::setCookie(self::$name, $value, time() + self::$expire);
            return true;
        }
        return false;
    }

    public static function get(string $name, ?string $returnType = null): mixed
    {
        if (!self::exists($name)) {
            return null;
        }

        self::$name = $name;
        $ret = self::isCrypted($name)
            ? json_decode(Crypter::decrypt($_COOKIE[$name]), true)
            : json_decode($_COOKIE[$name], true);

        return match ($returnType) {
            'expire' => $ret['expire'],
            'all' => $ret,
            default => $ret['values'],
        };
    }

    public static function isCrypted(string $name): bool
    {
        return !is_null($name) && Crypter::decrypt($_COOKIE[$name] ?? '') !== '';
    }

    public static function attach(string $key, mixed $newValue): bool
    {
        $oldData = self::get($key, 'all');
        if (!is_array($oldData)) {
            return false;
        }

        $newValue = is_array($newValue) ? $newValue : [$newValue];

        if ($oldData['encrypted'] == 1) {
            self::set($key, array_merge($oldData['values'], $newValue), $oldData['expire'], true)->encrypt();
        } else {
            self::set($key, array_merge($oldData['values'], $newValue), $oldData['expire'], true);
        }
        return true;
    }

    public static function detach(string $key, string|int $valueToDelete): bool
    {
        $oldData = self::get($key, 'all');
        if (!is_array($oldData)) {
            return false;
        }

        $values = $oldData['values'];
        unset($values[$valueToDelete]);

        if ($oldData['encrypted'] == 1) {
            self::set($key, $values, $oldData['expire'], true)->encrypt();
        } else {
            self::set($key, $values, $oldData['expire'], true);
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

    private static function setCookie(string $key, string $value, int $expiration): void
    {
        Response::instance()->setCookie($key, $value, $expiration);
    }
}
