<?php
declare(strict_types=1);

namespace Silver\Console;

/**
 * Parsed CLI invocation: `php silver <command> [args…] [--flag[=value]…]`.
 *
 * Positional arguments exclude `--flags`; flags accept `--name=value`
 * (string value) and bare `--name` (true).
 */
final class Input
{
    private string $command;
    /** @var list<string> */
    private array $arguments = [];
    /** @var array<string, string|true> */
    private array $flags = [];

    /** @param list<string> $argv full argv, script name at index 0 */
    public function __construct(array $argv)
    {
        $this->command = (string) ($argv[1] ?? '');

        foreach (array_slice($argv, 2) as $arg) {
            $arg = (string) $arg;
            if (str_starts_with($arg, '--')) {
                $body = substr($arg, 2);
                if (str_contains($body, '=')) {
                    [$k, $v] = explode('=', $body, 2);
                    $this->flags[$k] = $v;
                } else {
                    $this->flags[$body] = true;
                }
            } else {
                $this->arguments[] = $arg;
            }
        }
    }

    public function command(): string
    {
        return $this->command;
    }

    /** Positional argument by index (0 = first after the command). */
    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function flag(string $name, mixed $default = null): mixed
    {
        return $this->flags[$name] ?? $default;
    }

    public function hasFlag(string $name): bool
    {
        return array_key_exists($name, $this->flags);
    }

    /** @return array<string, string|true> */
    public function flags(): array
    {
        return $this->flags;
    }
}
