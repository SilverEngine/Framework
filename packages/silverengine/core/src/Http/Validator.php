<?php

declare(strict_types=1);

namespace Silver\Http;

/**
 * Rule-based form/input validator.
 *
 * Each {@see check()} call runs in isolation and returns a fresh
 * {@see ValidationResult} — no shared static state, no two-calls-in-a-
 * request clobbering. The class is instance-based so the container can
 * inject it into controllers and tests can substitute their own.
 *
 * Rule grammar:
 *   'email' => 'required|min:3|max:255'
 *   'pw'    => 'required|min:8'
 *   'pw2'   => 'match:pw'
 *
 * Supported rules: required, min:N, max:N, match:<otherField>.
 * Unknown rule names fail loudly (method-not-found) so callers can't
 * silently rely on rules that aren't implemented.
 */
final class Validator
{
    /**
     * Run every rule against $data and return the immutable result.
     *
     * @param array<string, mixed>  $data    field => value
     * @param array<string, string> $rules   field => 'rule1|rule2:arg|…'
     */
    public function check(array $data, array $rules): ValidationResult
    {
        $errors = [];

        foreach ($rules as $key => $ruleString) {
            $value = $data[$key] ?? null;

            foreach (explode('|', (string) $ruleString) as $clause) {
                $parts   = explode(':', $clause);
                $name    = $parts[0];
                $argStr  = $parts[1] ?? '';
                $args    = $argStr === '' ? [] : explode(',', $argStr);

                $msg = $this->{'check' . ucfirst($name)}($value, $data, ...$args);
                if ($msg !== false) {
                    $errors[$key][] = str_replace('KEY', $key, (string) $msg);
                }
            }
        }

        return new ValidationResult($errors);
    }

    // -- rule implementations -------------------------------------------
    // Each receives ($value, $data, ...$args) and returns a string message
    // when the rule fails, false otherwise. $data is passed so cross-field
    // rules (e.g. 'match') can compare against sibling values.

    /** @param array<string,mixed> $data */
    private function checkRequired(mixed $value, array $data): string|false
    {
        unset($data);
        return $value ? false : 'KEY is required!';
    }

    /** @param array<string,mixed> $data */
    private function checkMin(mixed $value, array $data, string $min = '0'): string|false
    {
        unset($data);
        return strlen((string) $value) < (int) $min
            ? "KEY must have at least {$min} characters."
            : false;
    }

    /** @param array<string,mixed> $data */
    private function checkMax(mixed $value, array $data, string $max = '0'): string|false
    {
        unset($data);
        return strlen((string) $value) > (int) $max
            ? "KEY must have less than {$max} characters."
            : false;
    }

    /** @param array<string,mixed> $data */
    private function checkMatch(mixed $value, array $data, string $otherField = ''): string|false
    {
        return (!isset($data[$otherField]) || $value != $data[$otherField])
            ? "{$otherField} does not match KEY"
            : false;
    }
}
