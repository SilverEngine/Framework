<?php
declare(strict_types=1);

namespace Silver\Console\Contracts;

use Silver\Console\Input;
use Silver\Console\Output;

/**
 * An app-defined (or framework builtin) console command.
 *
 * Register in config/commands.php, keyed by the command token:
 *
 *     return [
 *         'app:greet' => App\Commands\GreetCommand::class,
 *     ];
 *
 * Commands are resolved through the container, so constructor injection
 * works. `handle()` returns the process exit code (0 = success).
 */
interface Command
{
    public function description(): string;

    public function handle(Input $input, Output $output): int;
}
