<?php
declare(strict_types=1);

namespace Silver\Engine;

use RuntimeException;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Support\Scaffolder;

class CLI
{
    private string $cmd = '';
    private array $args = [];

    public function __construct(array $command)
    {
        if (!isset($command[1])) {
            $this->help();
            exit;
        }

        $this->cmd = $command[1];
        $this->args = $command;
        $this->run();
    }

    private function run(): void
    {
        match (Command::parse($this->cmd)) {
            Command::Generate => $this->make(),
            Command::Delete   => $this->delete(),
            Command::Migrate       => $this->migrate(),
            Command::Serve         => $this->serve(),
            Command::Optimize      => $this->optimize(),
            Command::OptimizeClear => $this->optimizeClear(),
            Command::Help          => $this->help(),
            null                   => $this->error('Command not found: ' . $this->cmd),
        };
    }

    private function optimize(): void
    {
        // Always rebuild from a clean state.
        $this->clearCaches();

        // Fresh config (cache just removed → build() path), needed to
        // know the route-file list.
        Env::construct(ROOT);

        $cacheDir = ROOT . 'storage/cache/';
        @mkdir($cacheDir, 0775, true);

        // Route cache: include the route files to populate the router,
        // then dump flat definitions — unless a Closure route forbids it.
        $router = app(Route::class);
        $router->reset();
        $loadRouteFile = static function (string $path, Route $route): void {
            include_once $path;
        };
        foreach (Env::get('routes', []) as $routeFile) {
            $loadRouteFile(ROOT . $routeFile . '.php', $router);
        }
        $defs = $router->definitions();
        if ($defs === null) {
            $this->error('Route cache skipped: a route uses a Closure action (not cacheable).');
        } else {
            file_put_contents(
                $cacheDir . 'routes.php',
                "<?php\n\nreturn " . var_export($defs, true) . ";\n",
                LOCK_EX,
            );
            $this->success('Routes cached      -> storage/cache/routes.php (' . count($defs) . ' routes)');
        }

        // Config cache.
        $cfg = Env::cacheConfig(ROOT);
        $this->success('Config cached      -> ' . str_replace(ROOT, '', $cfg));

        // Optimized Composer autoloader (classmap + PSR-4 fallback).
        if (file_exists(ROOT . 'composer.json')) {
            @exec('composer dump-autoload -o --quiet 2>&1', $out, $code);
            $this->success($code === 0
                ? 'Autoloader         -> composer dump-autoload -o'
                : 'Autoloader skipped -> run "composer dump-autoload -o" manually');
        }

        $this->success('Optimized. Run "php silver optimize:clear" after editing .env / Config / routes.');
    }

    private function optimizeClear(): void
    {
        $removed = $this->clearCaches();
        $this->success($removed > 0
            ? "Cleared {$removed} cache file(s) from storage/cache/."
            : 'Nothing to clear (no caches present).');
    }

    private function clearCaches(): int
    {
        $removed = 0;
        foreach (['config.php', 'routes.php'] as $f) {
            $path = ROOT . 'storage/cache/' . $f;
            if (is_file($path) && @unlink($path)) {
                $removed++;
            }
        }
        $removed += \Silver\Engine\Ghost\Compiler::clear();
        return $removed;
    }

    private function migrate(): void
    {
        if (empty($this->args[2])) {
            $path = ROOT . 'database/Migrations/';
            $files = array_diff(scandir($path), ['.', '..']);

            foreach ($files as $file) {
                $className = preg_replace('/\.php$/', '', $file);
                include_once $path . $className . '.php';
                $namespace = "\\Database\\Migrations\\" . $className;
                $namespace::up();
            }
        }
    }

    private function serve(): never
    {
        $bind = $this->args[2] ?? '127.0.0.1:8000';

        if (!str_contains($bind, ':')) {
            $bind = '127.0.0.1:' . $bind;
        }

        $docroot = ROOT . 'public';

        if (!is_dir($docroot)) {
            echo "\n Document root not found: {$docroot}\n";
            exit(1);
        }

        echo "\n SilverEngine dev server: http://{$bind}\n";
        echo " Document root: {$docroot}\n";
        echo " Press Ctrl+C to stop.\n\n";

        $cmd = escapeshellarg(PHP_BINARY)
            . ' -S ' . escapeshellarg($bind)
            . ' -t ' . escapeshellarg($docroot);

        passthru($cmd, $exitCode);
        exit($exitCode);
    }

    private function help(): void
    {
        echo <<<'HELP'

         SilverEngine framework — commands

         -----
         Dev server:        php silver serve [host:port]
         Migrations:        php silver migrate
         -----
         Optimize:          php silver optimize          # cache config + routes, -o autoload
         Clear caches:      php silver optimize:clear
         -----
         Generate:          php silver g <type> <name>
         Delete:            php silver d <type> <name>

         Types
           page         controller + Vue page + route line (Wisp)
           resource     model + repository + service + page (full layered stack)
           controller   PHP controller in app/Controllers/
           model        app/Models/<Name>.php
           service      app/Services/<Name>Service.php
           repository   app/Repositories/<Name>Repository.php
           middleware   app/Middlewares/<Name>.php
           provider     app/Providers/<Name>Provider.php
           observer     app/Observers/<Name>Observer.php
           dto          app/Dtos/<Name>Dto.php (readonly)
           vo           app/ValueObjects/<Name>.php (readonly)
           view         app/Resources/views/<name>.ghost.tpl
           helper       app/Helpers/<Name>.php
           facade       app/Facades/<Name>.php

         The web scaffolder at /new is the GUI for the same operations.

        HELP;
    }

    /**
     * `php silver g <type> <name>` — delegated to {@see Scaffolder}.
     * Single source of truth for every scaffolded artifact; the legacy
     * template files under app/Templates/ are no longer consulted.
     */
    private function make(): void
    {
        $type = $this->args[2] ?? '';
        $name = $this->args[3] ?? '';

        if ($type === '' || $name === '') {
            $this->error('Please enter complete command', Scaffolder::TYPES);
            return;
        }

        try {
            $result = app(Scaffolder::class)->create($type, $name);
            foreach ($result['created'] ?? [] as $path) {
                $this->success("  created  {$path}");
            }
            $this->success("{$result['type']} '{$result['name']}' created.");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), Scaffolder::TYPES);
        }
    }

    /** `php silver d <type> <name>` — inverse of `make()`. */
    private function delete(): void
    {
        $type = $this->args[2] ?? '';
        $name = $this->args[3] ?? '';

        if ($type === '' || $name === '') {
            $this->error('Please enter complete command', Scaffolder::TYPES);
            return;
        }

        try {
            $result = app(Scaffolder::class)->remove($type, $name);
            foreach ($result['removed'] ?? [] as $path) {
                $this->success("  removed  {$path}");
            }
            $this->success("{$result['type']} '{$result['name']}' removed.");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), Scaffolder::TYPES);
        }
    }

    private function error(string $message, array|false $help = false): void
    {
        echo "ERROR: {$message}\n";
        if ($help) {
            echo 'Available: ' . implode(', ', $help) . "\n";
        }
    }

    private function success(string $message): void
    {
        echo "{$message}\n";
    }

}
