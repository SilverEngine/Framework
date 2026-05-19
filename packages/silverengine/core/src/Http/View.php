<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Core\Contracts\RenderInterface;
use Silver\Core\App;
use Silver\Core\ErrorHandler;
use Silver\Engine\Ghost\Template;
use Silver\Exception\Exception;
use Silver\Support\Git;

class View implements RenderInterface
{
    private string $template;
    private array $data = [];

    /** @var array<string,mixed> Global data merged into every view and Wisp page. */
    private static array $shared = [];

    /** @var list<array{patterns:list<string>,callback:callable}> */
    private static array $composers = [];

    public function __construct(string $template, array $data = [])
    {
        $this->template = $template;
        foreach ($data as $key => $value) {
            $this->with($key, $value);
        }
    }

    /**
     * Share data globally — available as $key in every Ghost template and as
     * a shared prop in every Wisp page. Pass an array to share several keys.
     *
     * Note: shared values are also serialized into Wisp's client-side JSON,
     * so keep them client-safe.
     */
    public static function share(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            self::$shared[$k] = $v;
        }
    }

    /** @return array<string,mixed> */
    public static function shared(): array
    {
        return self::$shared;
    }

    /**
     * Register a composer: $callback(string $name): array is invoked whenever
     * a view/Wisp component whose name matches $patterns renders, and its
     * return value is merged into that render's data.
     *
     * @param string|list<string> $patterns Exact name or fnmatch wildcard
     *                                       ("Users/*", "errors.*").
     */
    public static function composer(string|array $patterns, callable $callback): void
    {
        self::$composers[] = [
            'patterns'  => array_values((array) $patterns),
            'callback'  => $callback,
        ];
    }

    /** Reset shared data + composers (tests, long-lived processes). */
    public static function flushShared(): void
    {
        self::$shared = [];
        self::$composers = [];
    }

    /**
     * Resolve shared data + matching composer output for a given view or
     * Wisp component name. Instance/prop data is layered on top by the caller.
     *
     * @return array<string,mixed>
     */
    public static function sharedFor(string $name): array
    {
        $data = self::$shared;

        foreach (self::$composers as $composer) {
            $matches = array_any(
                $composer['patterns'],
                static fn (string $pattern): bool => $pattern === $name || fnmatch($pattern, $name),
            );

            if ($matches) {
                $data = array_merge($data, (array) ($composer['callback'])($name));
            }
        }

        return $data;
    }

    public static function make(string $template, array $data = []): static
    {
        return new static($template, $data);
    }

    public static function error(string $template, array $data = []): static
    {
        return new static('errors' . DIRECTORY_SEPARATOR . $template, $data);
    }

    public static function demo(): static
    {
        return new static('demo.default', [
            '_branch_' => Git::test(),
        ]);
    }

    public function with(string $key, mixed $value = true): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function withComponent(mixed $value = true, string|false $key = false): static
    {
        $key = $key ? 'component_' . $key : 'component_payload';
        $this->data[$key] = $value;
        return $this;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function render(): string
    {
        return ErrorHandler::withFilter(
            E_ALL ^ E_NOTICE,
            function (): string {
                $name = str_replace('.', '/', $this->template);
                // Instance data wins over shared/composer data.
                $data = array_merge(self::sharedFor($this->template), $this->data);

                $extensions = ['.ghost.php', '.ghost.tpl', '.php', '.html'];

                foreach ($extensions as $ext) {
                    $target = App::instance()->find('Views/' . $name . $ext);
                    if ($target === null) {
                        continue;
                    }

                    if ($ext === '.html') {
                        return file_get_contents($target);
                    }

                    if ($ext === '.php') {
                        try {
                            foreach ($data as $key => $value) {
                                $$key = $value;
                            }
                            ob_start();
                            include $target;
                            $content = ob_get_contents();
                        } finally {
                            ob_end_clean();
                        }
                        return $content;
                    }

                    // .ghost.php or .ghost.tpl
                    return (new Template($target, $data))->render();
                }

                throw new Exception("Template $name not found.");
            },
        );
    }

    public function __call(string $key, array $args): static
    {
        if (str_starts_with($key, 'with')) {
            $key = lcfirst(substr($key, 4));
            return $this->with($key, $args[0] ?? true);
        }

        throw new Exception("Call undefined method " . static::class . "::" . $key . '()');
    }
}
