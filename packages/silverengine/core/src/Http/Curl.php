<?php
declare(strict_types=1);

namespace Silver\Http;

final class Curl
{
    public static function get(string $url, ?array $get = null, array $options = []): mixed
    {
        $defaults = [
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options + $defaults);
        $result = curl_exec($ch);
        if ($result === false) {
            trigger_error(curl_error($ch));
        }

        return json_decode($result ?: '');
    }

    public static function post(string $url, ?array $post = null, array $options = []): string|false
    {
        $defaults = [
            CURLOPT_POST           => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_URL            => $url,
            CURLOPT_FRESH_CONNECT  => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE   => 1,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_POSTFIELDS     => http_build_query($post ?? []),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options + $defaults);
        $result = curl_exec($ch);
        if ($result === false) {
            trigger_error(curl_error($ch));
        }

        return $result;
    }
}
