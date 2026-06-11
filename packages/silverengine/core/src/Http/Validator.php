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
 *   alpha, alphanumeric, regex:/pattern/, same:<field>, different:<field>,
 *   file, image, mimes:ext1,ext2,
 *   required_if:<field>,<value>, required_unless:<field>,<value>,
 *   plus Closure rules via the array syntax (see check()).
 *
 * File values ({@see UploadedFile}) flip min:/max: to kilobytes of
 * file size; image/mimes judge the REAL mime (finfo), not the
 * client-supplied name or type.
 *
 * Unknown rule names fail loudly (method-not-found) so callers can't
 * silently rely on rules that aren't implemented.
 */
final class Validator
{
    /**
     * Run every rule against $data and return the immutable result.
     *
     * Rules come as a pipe string ('required|email|max:255') or as a
     * list mixing single rule strings and Closures:
     *
     *   'slug' => ['required', 'max:64',
     *              fn (mixed $value, array $data): string|false =>
     *                  taken($value) ? 'KEY is taken.' : false]
     *
     * A closure returns the failure message (string, 'KEY' is replaced
     * with the field name) or false/null to pass.
     *
     * @param array<string, mixed>                       $data     field => value
     * @param array<string, string|array<string|\Closure>> $rules    field => rules
     * @param array<string, string>                      $messages optional message overrides
     *                                                            keyed by 'field' or 'field.rule'
     */
    public function check(array $data, array $rules, array $messages = []): ValidationResult
    {
        $errors = [];

        foreach ($rules as $key => $ruleSpec) {
            $value = $data[$key] ?? null;

            // `nullable` lets a missing/blank value bypass the remaining rules
            // (except for explicit `required`, which is always evaluated first
            // anyway by virtue of usually appearing first in the chain).
            $clauses    = is_string($ruleSpec) ? explode('|', $ruleSpec) : $ruleSpec;
            $isNullable = in_array('nullable', $clauses, true);
            $isBlank    = $value === null || $value === '';

            if ($isNullable && $isBlank) {
                continue;
            }

            foreach ($clauses as $clause) {
                if ($clause instanceof \Closure) {
                    $msg = $clause($value, $data);
                    if (is_string($msg) && $msg !== '') {
                        $errors[$key][] = str_replace('KEY', $key, $msg);
                    }
                    continue;
                }

                if ($clause === '' || $clause === 'nullable') {
                    continue;
                }

                $parts  = explode(':', $clause, 2);
                $name   = $parts[0];
                $argStr = $parts[1] ?? '';
                $args   = $argStr === '' ? [] : explode(',', $argStr);

                // 'required_if' → checkRequiredIf (snake → studly).
                $method = 'check' . str_replace('_', '', ucwords($name, '_'));
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

    /**
     * Required only when $data[$field] equals $expected (loose string
     * compare — form data arrives as strings).
     *
     * @param array<string,mixed> $data
     */
    private function checkRequiredIf(mixed $value, array $data, string $field = '', string $expected = ''): string|false
    {
        if ((string) ($data[$field] ?? '') !== $expected) {
            return false;
        }
        return $this->checkRequired($value, $data) === false
            ? false
            : "KEY is required when {$field} is {$expected}.";
    }

    /**
     * Required unless $data[$field] equals $expected.
     *
     * @param array<string,mixed> $data
     */
    private function checkRequiredUnless(mixed $value, array $data, string $field = '', string $expected = ''): string|false
    {
        if ((string) ($data[$field] ?? '') === $expected) {
            return false;
        }
        return $this->checkRequired($value, $data) === false
            ? false
            : "KEY is required unless {$field} is {$expected}.";
    }

    /** @param array<string,mixed> $data */
    private function checkMin(mixed $value, array $data, string $min = '0'): string|false
    {
        unset($data);
        if ($value instanceof UploadedFile) {
            return $value->size() < (int) $min * 1024
                ? "KEY must be at least {$min} kilobytes."
                : false;
        }
        return strlen((string) $value) < (int) $min
            ? "KEY must have at least {$min} characters."
            : false;
    }

    /** @param array<string,mixed> $data */
    private function checkMax(mixed $value, array $data, string $max = '0'): string|false
    {
        unset($data);
        if ($value instanceof UploadedFile) {
            return $value->size() > (int) $max * 1024
                ? "KEY may not be larger than {$max} kilobytes."
                : false;
        }
        return strlen((string) $value) > (int) $max
            ? "KEY must have less than {$max} characters."
            : false;
    }

    /** @param array<string,mixed> $data */
    private function checkFile(mixed $value, array $data): string|false
    {
        unset($data);
        return ($value instanceof UploadedFile && $value->isValid())
            ? false
            : 'KEY must be a successfully uploaded file.';
    }

    /** @param array<string,mixed> $data */
    private function checkImage(mixed $value, array $data): string|false
    {
        unset($data);
        // Content-derived mime — a renamed .gif full of text fails here.
        return ($value instanceof UploadedFile && $value->isValid() && $value->isImage())
            ? false
            : 'KEY must be an image.';
    }

    /**
     * Allowed extensions, matched against the extension derived from
     * the file's REAL mime type (not the client-supplied name).
     *
     * @param array<string,mixed> $data
     */
    private function checkMimes(mixed $value, array $data, string ...$extensions): string|false
    {
        unset($data);
        $allowed = array_map(
            static fn (string $e): string => $e === 'jpeg' ? 'jpg' : strtolower($e),
            $extensions,
        );
        return ($value instanceof UploadedFile
                && $value->isValid()
                && in_array($value->extension(), $allowed, true))
            ? false
            : 'KEY must be a file of type: ' . implode(', ', $extensions) . '.';
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
