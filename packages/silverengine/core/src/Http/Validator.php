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
 *   'email'                 => 'required|email|max:255'
 *   'password'              => 'required|min:8|confirmed'
 *   'role'                  => 'required|in:admin,user,guest'
 *   'age'                   => 'nullable|integer|min:18'
 *
 * Supported rules:
 *   required, nullable, min:N, max:N, between:A,B, match:<field>,
 *   confirmed, in:a,b,c, email, url, integer, numeric, string,
 *   alpha, alphanumeric, regex:/pattern/, same:<field>, different:<field>.
 *
 * Unknown rule names fail loudly (method-not-found) so callers can't
 * silently rely on rules that aren't implemented.
 */
final class Validator
{
    /**
     * Run every rule against $data and return the immutable result.
     *
     * @param array<string, mixed>          $data     field => value
     * @param array<string, string>         $rules    field => 'rule1|rule2:arg|…'
     * @param array<string, string>         $messages optional message overrides keyed by
     *                                                'field' or 'field.rule'
     */
    public function check(array $data, array $rules, array $messages = []): ValidationResult
    {
        $errors = [];

        foreach ($rules as $key => $ruleString) {
            $value = $data[$key] ?? null;

            // `nullable` lets a missing/blank value bypass the remaining rules
            // (except for explicit `required`, which is always evaluated first
            // anyway by virtue of usually appearing first in the chain).
            $clauses  = explode('|', (string) $ruleString);
            $isNullable = in_array('nullable', $clauses, true);
            $isBlank    = $value === null || $value === '';

            if ($isNullable && $isBlank) {
                continue;
            }

            foreach ($clauses as $clause) {
                if ($clause === '' || $clause === 'nullable') {
                    continue;
                }

                $parts  = explode(':', $clause, 2);
                $name   = $parts[0];
                $argStr = $parts[1] ?? '';
                $args   = $argStr === '' ? [] : explode(',', $argStr);

                $method = 'check' . ucfirst($name);
                if (!method_exists($this, $method)) {
                    throw new \InvalidArgumentException("Unknown validation rule: {$name}");
                }

                $msg = $this->{$method}($value, $data, ...$args);
                if ($msg !== false) {
                    $errors[$key][] = $this->resolveMessage($key, $name, (string) $msg, $messages);
                }
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * @param array<string, string> $messages
     */
    private function resolveMessage(string $field, string $rule, string $default, array $messages): string
    {
        $template = $messages[$field . '.' . $rule]
            ?? $messages[$field]
            ?? $default;

        return str_replace('KEY', $field, $template);
    }

    // -- rule implementations -------------------------------------------
    // Each receives ($value, $data, ...$args) and returns a string message
    // when the rule fails, false otherwise. $data is passed so cross-field
    // rules (e.g. 'match', 'confirmed', 'same') can compare siblings.

    /** @param array<string,mixed> $data */
    private function checkRequired(mixed $value, array $data): string|false
    {
        unset($data);
        if (is_string($value)) {
            return trim($value) === '' ? 'KEY is required.' : false;
        }
        if (is_array($value)) {
            return $value === [] ? 'KEY is required.' : false;
        }
        return ($value === null) ? 'KEY is required.' : false;
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
    private function checkBetween(mixed $value, array $data, string $min = '0', string $max = '0'): string|false
    {
        $a = $this->checkMin($value, $data, $min);
        if ($a !== false) {
            return $a;
        }
        return $this->checkMax($value, $data, $max);
    }

    /** @param array<string,mixed> $data */
    private function checkMatch(mixed $value, array $data, string $otherField = ''): string|false
    {
        return (!isset($data[$otherField]) || $value != $data[$otherField])
            ? "{$otherField} does not match KEY."
            : false;
    }

    /** @param array<string,mixed> $data */
    private function checkSame(mixed $value, array $data, string $otherField = ''): string|false
    {
        return $this->checkMatch($value, $data, $otherField);
    }

    /** @param array<string,mixed> $data */
    private function checkDifferent(mixed $value, array $data, string $otherField = ''): string|false
    {
        if (!array_key_exists($otherField, $data)) {
            return false;
        }
        return ($value === $data[$otherField])
            ? "KEY must be different from {$otherField}."
            : false;
    }

    /** @param array<string,mixed> $data */
    private function checkConfirmed(mixed $value, array $data): string|false
    {
        // Convention: `password` is confirmed by `password_confirmation`.
        $other = $data['password_confirmation'] ?? null;
        // If we can't tell the field name, fall back to value comparison.
        return ($value === $other) ? false : 'KEY confirmation does not match.';
    }

    /** @param array<string,mixed> $data */
    private function checkEmail(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false; // honour `required` separately
        }
        return filter_var((string) $value, FILTER_VALIDATE_EMAIL)
            ? false
            : 'KEY must be a valid email address.';
    }

    /** @param array<string,mixed> $data */
    private function checkUrl(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false;
        }
        return filter_var((string) $value, FILTER_VALIDATE_URL)
            ? false
            : 'KEY must be a valid URL.';
    }

    /** @param array<string,mixed> $data */
    private function checkIn(mixed $value, array $data, string ...$choices): string|false
    {
        unset($data);
        if ($value === null) {
            return false;
        }
        return in_array((string) $value, $choices, true)
            ? false
            : 'KEY must be one of: ' . implode(', ', $choices) . '.';
    }

    /** @param array<string,mixed> $data */
    private function checkInteger(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? false
            : 'KEY must be an integer.';
    }

    /** @param array<string,mixed> $data */
    private function checkNumeric(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false;
        }
        return is_numeric($value) ? false : 'KEY must be numeric.';
    }

    /** @param array<string,mixed> $data */
    private function checkString(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null) {
            return false;
        }
        return is_string($value) ? false : 'KEY must be a string.';
    }

    /** @param array<string,mixed> $data */
    private function checkAlpha(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false;
        }
        return preg_match('/^[\pL\pM]+$/u', (string) $value)
            ? false
            : 'KEY may only contain letters.';
    }

    /** @param array<string,mixed> $data */
    private function checkAlphanumeric(mixed $value, array $data): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false;
        }
        return preg_match('/^[\pL\pM\pN]+$/u', (string) $value)
            ? false
            : 'KEY may only contain letters and numbers.';
    }

    /** @param array<string,mixed> $data */
    private function checkRegex(mixed $value, array $data, string $pattern = ''): string|false
    {
        unset($data);
        if ($value === null || $value === '') {
            return false;
        }
        if ($pattern === '') {
            throw new \InvalidArgumentException('regex rule requires a pattern argument.');
        }
        return preg_match($pattern, (string) $value)
            ? false
            : 'KEY format is invalid.';
    }
}
