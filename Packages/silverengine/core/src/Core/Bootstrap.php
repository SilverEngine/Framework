<?php
declare(strict_types=1);

namespace Silver\Core;

final class Bootstrap
{
    public readonly array $url;

    public function __construct()
    {
        $this->url = self::grabUrl();
    }

    private static function grabUrl(): array
    {
        $url = $_GET['url'] ?? '';

        if ($url !== '') {
            return explode('/', filter_var(rtrim($url, '/'), FILTER_SANITIZE_URL));
        }

        return [];
    }
}
