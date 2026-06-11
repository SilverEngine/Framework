<?php
declare(strict_types=1);

namespace Silver\Console;

use Silver\Console\Contracts\Command;
use Silver\Core\Env;

/**
 * Command registry: merges the framework builtins with the app's
 * config/commands.php map (token => class). App entries win on
 * collision, so a builtin can be overridden.
 */
final class Registry
{
    /** Framework builtins (token => class). */
    private const BUILTINS = [
        'route:list' => Commands\RouteListCommand::class,
        'db:show'    => Commands\DbShowCommand::class,
        'db:table'   => Commands\DbTableCommand::class,
        'db:seed'    => Commands\DbSeedCommand::class,
    ];

    /** @return array<string, class-string<Command>> */
    public function all(): array
    {
        $configured = Env::get('commands', []);
        if (is_object($configured)) {
            $configured = (array) $configured;
        }

        /** @var array<string, class-string<Command>> */
        return array_merge(self::BUILTINS, (array) $configured);
    }

    /** @return class-string<Command>|null */
    public function find(string $token): ?string
    {
        return $this->all()[$token] ?? null;
    }

    /** Resolve via the container and run. Returns the exit code. */
    public function run(string $token, Input $input, Output $output): int
    {
        $class = $this->find($token);
        if ($class === null) {
            $output->error("Command not found: {$token}");
            return 1;
        }

        /** @var Command $command */
        $command = app($class);

        return $command->handle($input, $output);
    }
}
