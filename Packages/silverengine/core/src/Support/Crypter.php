<?php
declare(strict_types=1);

namespace Silver\Support;

use Silver\Core\Env;

final class Crypter
{
    public static function crypt(string $string, ?string $password = null, string $cipher = 'AES-256-ECB'): string
    {
        $password ??= (string) Env::get('app_key');
        return base64_encode(openssl_encrypt($string, $cipher, $password));
    }

    public static function decrypt(string $string, ?string $password = null, string $cipher = 'AES-256-ECB'): string
    {
        $password ??= (string) Env::get('app_key');
        return openssl_decrypt(base64_decode($string), $cipher, $password) ?: '';
    }

    public static function makePassword(int $len = 16, int $charsType = 1): string
    {
        $alphabet = match ($charsType) {
            2 => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
            3 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            4 => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            5 => '1234567890',
            default => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!#$%&()=?*+{}@',
        };

        $password = [];
        for ($i = 0; $i < $len; $i++) {
            $password[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return implode($password);
    }

    public static function makeHash(string $password, ?string $alg = null): string
    {
        if ($alg === null) {
            return password_hash($password, PASSWORD_DEFAULT);
        }
        return hash($alg, $password);
    }

    public static function verifyHash(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
