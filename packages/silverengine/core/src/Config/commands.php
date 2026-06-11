<?php

/*
|--------------------------------------------------------------------------
| Console commands
|--------------------------------------------------------------------------
| Map of command token => class implementing
| Silver\Console\Contracts\Command. Tokens the `silver` CLI doesn't
| recognise as builtins are looked up here (after the framework extras
| route:list / db:show / db:table — an app entry with the same token
| overrides the builtin).
|
| Commands are resolved through the container, so constructor injection
| works. handle(Input, Output) returns the process exit code.
|
| Accessed via Env::get('commands').
*/

return [
    // 'app:greet' => App\Commands\GreetCommand::class,
];
