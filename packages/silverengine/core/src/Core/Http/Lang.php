<?php
declare(strict_types=1);

namespace Silver\Core\Http;

use Silver\Http\Session;

final class Lang
{
    public static function get(string $relativePath): mixed
    {
        $parts = explode('.', $relativePath);
        $lang = Session::exists('lang') ? Session::get('lang') : 'en';

        $file = include ROOT . 'storage/Lang/' . $lang . '/' . $parts[0] . '.php';

        return $file[$parts[1]] ?? null;
    }

    public static function set(string $name): void
    {
        Session::set('lang', $name);
    }
}
