<?php
declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('is_class')) {
    /**
     * True when `$value` is a string naming an existing (autoloadable)
     * class. Centralises the `is_string($x) && class_exists($x)` idiom
     * that shows up everywhere config-driven class names are validated
     * (middleware lists, fetch-style hints, provider configs, etc.).
     */
    function is_class(mixed $value): bool
    {
        return is_string($value) && class_exists($value);
    }
}