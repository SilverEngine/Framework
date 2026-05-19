<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Exception\Exception;

final class Redirect
{
    public static function to(string $url, bool $permanent = false): never
    {
        if (headers_sent() === false) {
            header('Location: ' . $url, true, $permanent ? 301 : 302);
        }
        exit();
    }

    public static function back(?string $fallback = null): never
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            self::to($_SERVER['HTTP_REFERER']);
        }

        if ($fallback !== null) {
            self::to($fallback);
        }

        throw new Exception("Unknown referer.");
    }
}
