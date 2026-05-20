<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

/**
 * Resolves Vite assets for the `{{ vite() }}` Ghost directive.
 *
 * Dev:  a `public/build/hot` file (written by the dev-server plugin in
 *       vite.config.ts) contains the dev-server origin. Emits the HMR client
 *       plus the entry as native ES modules served by Vite.
 * Prod: reads Vite's `public/build/.vite/manifest.json` and emits the hashed
 *       entry script plus any imported CSS, served from `/build/`.
 */
final class Vite
{
    private const ENTRY = 'app/Resources/js/app.ts';
    private const CSS_ENTRY = 'app/Resources/css/app.css';

    /** @var array<string,mixed>|null */
    private static ?array $manifestCache = null;
    private static ?string $hotOriginCache = null;
    private static ?string $versionCache = null;

    public static function hotFile(): string
    {
        return ROOT . 'public/build/hot';
    }

    public static function manifestFile(): string
    {
        return ROOT . 'public/build/.vite/manifest.json';
    }

    public static function isHot(): bool
    {
        return is_file(self::hotFile());
    }

    private static function hotOrigin(): string
    {
        return self::$hotOriginCache ??= rtrim(
            trim((string) file_get_contents(self::hotFile())),
            '/',
        );
    }

    /** @return array<string,mixed> */
    private static function manifest(): array
    {
        if (self::$manifestCache !== null) {
            return self::$manifestCache;
        }
        $file = self::manifestFile();
        if (!is_file($file)) {
            return self::$manifestCache = [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return self::$manifestCache = is_array($data) ? $data : [];
    }

    /**
     * Reset all caches. Used by tests / long-running workers between requests.
     */
    public static function reset(): void
    {
        self::$manifestCache = null;
        self::$hotOriginCache = null;
        self::$versionCache = null;
    }

    /**
     * Asset version used for the Inertia version handshake.
     * Null in dev (no manifest) so the version check is skipped.
     */
    public static function version(): ?string
    {
        if (self::$versionCache !== null) {
            return self::$versionCache;
        }
        $manifest = self::manifestFile();
        if (!is_file($manifest)) {
            return null;
        }
        return self::$versionCache = substr((string) md5_file($manifest), 0, 12);
    }

    public static function tags(): string
    {
        return self::isHot() ? self::devTags() : self::prodTags();
    }

    private static function devTags(): string
    {
        $origin = self::hotOrigin();

        return implode("\n", [
            '<script type="module" src="' . $origin . '/@vite/client"></script>',
            '<script type="module" src="' . $origin . '/' . self::ENTRY . '"></script>',
        ]);
    }

    private static function prodTags(): string
    {
        $data = self::manifest();
        if ($data === []) {
            return '<!-- vite: run `npm run build` (no manifest found) -->';
        }
        $entry = $data[self::ENTRY] ?? null;
        if ($entry === null) {
            return '<!-- vite: entry "' . self::ENTRY . '" missing from manifest -->';
        }

        $base = (defined('URL') ? URL : '') . '/build/';
        $tags = [];

        foreach ($entry['css'] ?? [] as $css) {
            $tags[] = '<link rel="stylesheet" href="' . $base . $css . '">';
        }

        $tags[] = '<script type="module" src="' . $base . $entry['file'] . '"></script>';

        return implode("\n", $tags);
    }

    /**
     * JS-free Tailwind stylesheet for server-rendered Ghost pages (welcome,
     * errors). Dev: Vite injects the CSS via its module (no Vue). Prod: a
     * plain <link> from the manifest. Not built: empty string — the page
     * still renders (unstyled) and never errors.
     */
    public static function cssTags(): string
    {
        if (self::isHot()) {
            $origin = self::hotOrigin();

            return implode("\n", [
                '<script type="module" src="' . $origin . '/@vite/client"></script>',
                '<script type="module" src="' . $origin . '/' . self::CSS_ENTRY . '"></script>',
            ]);
        }

        $data = self::manifest();
        $entry = $data[self::CSS_ENTRY] ?? null;
        if ($entry === null) {
            return '';
        }

        $base = (defined('URL') ? URL : '') . '/build/';
        $tags = [];

        foreach ($entry['css'] ?? [] as $css) {
            $tags[] = '<link rel="stylesheet" href="' . $base . $css . '">';
        }
        if (str_ends_with((string) ($entry['file'] ?? ''), '.css')) {
            $tags[] = '<link rel="stylesheet" href="' . $base . $entry['file'] . '">';
        }

        return implode("\n", $tags);
    }
}
