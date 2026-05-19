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

    public function __construct(string $template, array $data = [])
    {
        $this->template = $template;
        foreach ($data as $key => $value) {
            $this->with($key, $value);
        }
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
                            foreach ($this->data as $key => $value) {
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
                    return (new Template($target, $this->data))->render();
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
