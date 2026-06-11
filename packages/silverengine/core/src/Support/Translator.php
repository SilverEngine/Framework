<?php
declare(strict_types=1);

namespace Silver\Support;

/**
 * Localization v2 (G3). Lines live in `{langPath}/{locale}/{file}.php`
 * (the same layout the legacy `Lang` class reads), keys are
 * `file.path.to.line` with arbitrary nesting:
 *
 *     trans('messages.welcome', ['name' => 'Lex'])
 *     trans_choice('messages.apples', 3)
 *
 * Interpolation replaces `:key` with the value — `:KEY` upper-cases it
 * and `:Key` ucfirsts it. choice() supports plain `singular|plural`
 * strings plus Laravel-style `{n}` exact and `[a,b]` / `[a,*]` range
 * conditions, and always injects `:count`. A miss tries the fallback
 * locale, then returns the key verbatim (visible, greppable).
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> "locale/file" → lines */
    private array $loaded = [];

    public function __construct(
        private readonly string $langPath,
        private string $locale = 'en',
        private readonly string $fallback = 'en',
    ) {}

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /** @param array<string, mixed> $replace */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $line = $this->line($key, $locale ?? $this->locale)
            ?? $this->line($key, $this->fallback)
            ?? $key;

        return $this->interpolate($line, $replace);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->line($key, $locale ?? $this->locale) !== null
            || $this->line($key, $this->fallback) !== null;
    }

    /**
     * Pluralized line: segments split on '|', picked by $count.
     *
     * @param array<string, mixed> $replace
     */
    public function choice(string $key, int|float $count, array $replace = [], ?string $locale = null): string
    {
        $line = $this->line($key, $locale ?? $this->locale)
            ?? $this->line($key, $this->fallback);

        if ($line === null) {
            return $key;
        }

        $segments = explode('|', $line);
        $chosen   = $this->matchConditional($segments, $count)
            ?? $this->matchPositional($segments, $count);

        return $this->interpolate($chosen, ['count' => $count] + $replace);
    }

    // ---------- internals ----------

    /** The raw line for $key in $locale, or null. */
    private function line(string $key, string $locale): ?string
    {
        $parts = explode('.', $key);
        $file  = array_shift($parts);
        if ($parts === []) {
            return null;
        }

        $lines  = $this->load($locale, $file);
        $cursor = $lines;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return is_string($cursor) ? $cursor : null;
    }

    /** @return array<string, mixed> */
    private function load(string $locale, string $file): array
    {
        $cacheKey = $locale . '/' . $file;
        if (isset($this->loaded[$cacheKey])) {
            return $this->loaded[$cacheKey];
        }

        $path  = rtrim($this->langPath, '/') . '/' . $locale . '/' . $file . '.php';
        $lines = is_file($path) ? include $path : [];

        return $this->loaded[$cacheKey] = is_array($lines) ? $lines : [];
    }

    /** @param array<string, mixed> $replace */
    private function interpolate(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        // Longest keys first, so :namePlural never half-matches :name.
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $search = $found = [];
        foreach ($replace as $name => $value) {
            $value = (string) (is_scalar($value) || $value instanceof \Stringable ? $value : '');
            $search[] = ':' . $name;
            $found[]  = $value;
            $search[] = ':' . strtoupper($name);
            $found[]  = strtoupper($value);
            $search[] = ':' . ucfirst($name);
            $found[]  = ucfirst($value);
        }

        return str_replace($search, $found, $line);
    }

    /**
     * First segment whose `{n}` / `{a,b}` / `[a,b]` / `[a,*]` condition
     * matches $count (condition stripped), or null.
     *
     * @param list<string> $segments
     */
    private function matchConditional(array $segments, int|float $count): ?string
    {
        foreach ($segments as $segment) {
            if (preg_match('/^\{(.*?)\}\s*(.*)$/s', $segment, $m) === 1) {
                $values = array_map(floatval(...), explode(',', $m[1]));
                if (in_array((float) $count, $values, true)) {
                    return $m[2];
                }
            } elseif (preg_match('/^\[\s*([\d.]+)\s*,\s*([\d.]+|\*)\s*\]\s*(.*)$/s', $segment, $m) === 1) {
                $from = (float) $m[1];
                $to   = $m[2] === '*' ? INF : (float) $m[2];
                if ($count >= $from && $count <= $to) {
                    return $m[3];
                }
            }
        }
        return null;
    }

    /**
     * No matching condition: classic positional pick — one segment fits
     * all, two = singular|plural, more clamps to the last.
     *
     * @param list<string> $segments
     */
    private function matchPositional(array $segments, int|float $count): string
    {
        // Strip any (unmatched) condition prefixes before picking.
        $plain = array_values(array_map(
            static fn (string $s): string => (string) preg_replace('/^(\{.*?\}|\[.*?\])\s*/s', '', $s),
            $segments,
        ));

        if (count($plain) === 1) {
            return $plain[0];
        }

        $index = (float) $count === 1.0 ? 0 : 1;
        return $plain[min($index, count($plain) - 1)];
    }
}
