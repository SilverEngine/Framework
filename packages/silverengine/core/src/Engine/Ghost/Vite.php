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

    /**
     * Asset version used for the Inertia version handshake.
     * Null in dev (no manifest) so the version check is skipped.
     */
    public static function version(): ?string
    {
        $manifest = self::manifestFile();

        return is_file($manifest) ? substr((string) md5_file($manifest), 0, 12) : null;
    }

    public static function tags(): string
    {
        return self::isHot() ? self::devTags() : self::prodTags();
    }

    private static function devTags(): string
    {
        $origin = rtrim(trim((string) file_get_contents(self::hotFile())), '/');

        return implode("\n", [
            '<script type="module" src="' . $origin . '/@vite/client"></script>',
            '<script type="module" src="' . $origin . '/' . self::ENTRY . '"></script>',
        ]);
    }

    private static function prodTags(): string
    {
        $manifest = self::manifestFile();

        if (!is_file($manifest)) {
            return '<!-- vite: run `npm run build` (no manifest found) -->';
        }

        $data = json_decode((string) file_get_contents($manifest), true) ?: [];
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
            $origin = rtrim(trim((string) file_get_contents(self::hotFile())), '/');

            return implode("\n", [
                '<script type="module" src="' . $origin . '/@vite/client"></script>',
                '<script type="module" src="' . $origin . '/' . self::CSS_ENTRY . '"></script>',
            ]);
        }

        $manifest = self::manifestFile();
        if (!is_file($manifest)) {
            return '';
        }

        $data = json_decode((string) file_get_contents($manifest), true) ?: [];
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
