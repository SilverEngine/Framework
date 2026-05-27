<?php
declare(strict_types=1);

namespace Silver\Http;

final class Validator
{
    private static array $data = [];
    private static array $errors = [];

    public static function check(array $data, array $validators): array
    {
        self::$data = $data;
        self::$errors = [];
        $errors = [];

        foreach ($validators as $key => $validator) {
            $value = $data[$key] ?? null;

            foreach (explode('|', $validator) as $valid) {
                $funArgs = explode(':', $valid);
                $funArgs[] = '';

                $fun = 'check' . ucfirst($funArgs[0]);
                $ret = call_user_func_array([self::class, $fun], array_merge([$value], explode(',', $funArgs[1])));

                if ($ret) {
                    $ret = str_replace('KEY', $key, $ret);
                    $errors[] = $ret;
                    self::$errors[$key] ??= [];
                    self::$errors[$key][] = $ret;
                }
            }
        }

        return $errors;
    }

    public static function get(string $key): array
    {
        return self::$errors[$key] ?? [];
    }

    public static function pass(): bool
    {
        return empty(self::$errors);
    }

    private static function checkMin(mixed $value, string $min): string|false
    {
        return strlen((string) $value) < (int) $min
            ? "KEY must have at least $min characters."
            : false;
    }

    private static function checkMax(mixed $value, string $max): string|false
    {
        return strlen((string) $value) > (int) $max
            ? "KEY must have less than $max characters."
            : false;
    }

    private static function checkRequired(mixed $value): string|false
    {
        return !$value ? "KEY is required!" : false;
    }

    private static function checkMatch(mixed $value, string $key): string|false
    {
        return (!isset(self::$data[$key]) || $value != self::$data[$key])
            ? "$key does not match KEY"
            : false;
    }
}
