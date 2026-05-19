<?php
declare(strict_types=1);

namespace Silver\Engine;

use Silver\Engine\Ghost\Template;

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
            Command::Migrate  => $this->migrate(),
            Command::Serve    => $this->serve(),
            Command::Help     => $this->help(),
            null              => $this->error('Command not found: ' . $this->cmd),
        };
    }

    private function migrate(): void
    {
        if (empty($this->args[2])) {
            $path = ROOT . 'Database/Migrations/';
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

         SilverEngine framework - commands

         -----
         Start dev server: php silver serve [host:port]
         Run migrations:   php silver migrate
         -----
         Create CRUD resource: php silver g resource {name}
         Create Controller:    php silver g controller {name}
         Create Model:         php silver g model {name}
         Create View:          php silver g view {name}
         Create Facade:        php silver g facade {name}
         Create Helper:        php silver g helper {name}
         -----
         Delete CRUD resource: php silver d resource {name}

        HELP;
    }

    private function make(): void
    {
        $type = $this->args[2] ?? '';
        $name = $this->args[3] ?? '';

        if ($type === 'resource') {
            foreach (['controller', 'view', 'model'] as $t) {
                $this->generate($t, $name);
            }
        } elseif (in_array($type, ['controller', 'model', 'view', 'helper', 'facade'], true)) {
            $this->generate($type, $name);
        } else {
            $this->error('Please enter complete command', ['resource', 'controller', 'model', 'view', 'helper', 'facade']);
        }
    }

    private function delete(): void
    {
        $type = $this->args[2] ?? '';
        $name = $this->args[3] ?? '';

        if ($type === 'resource') {
            foreach (['model', 'view', 'controller'] as $t) {
                $this->deleteResources($t, $name);
            }
        } else {
            $this->error('Please enter complete command', ['resource', 'controller', 'model', 'view', 'helper', 'facade']);
        }
    }

    private function generate(string $type, string $name): void
    {
        [$template, $destination] = $this->resolvePaths($type, $name);

        if (!file_exists($template)) {
            $this->error('Template is missing');
            return;
        }

        if ($type === 'view') {
            $this->generateView($destination, $type, $name);
            return;
        }

        if (file_exists($destination)) {
            $this->error('File exists!');
            return;
        }

        $this->generateFile($template, $destination, $type, $name);
    }

    private function deleteResources(string $type, string $name): void
    {
        [, $destination] = $this->resolvePaths($type, $name);

        if ($type === 'controller') {
            $this->fixRoutes($name, false);
        }

        if (!file_exists($destination)) {
            $this->error('File does not exist!');
            return;
        }

        unlink($destination);
        $this->success("{$type} {$name} successfully deleted. ({$destination})");
    }

    private function resolvePaths(string $type, string $name): array
    {
        return match ($type) {
            'model', 'controller' => [
                ROOT . 'App/Templates/' . ucfirst($type) . '.ghost.tpl',
                ROOT . 'App/' . ucfirst($type) . 's/' . ucfirst($name) . ucfirst($type) . '.php',
            ],
            'view' => [
                ROOT . 'App/Templates/View.ghost.tpl',
                ROOT . 'App/Views/' . str_replace('.', '/', strtolower($name)) . '.ghost.tpl',
            ],
            'helper' => [
                ROOT . 'App/Templates/Helper.ghost.tpl',
                ROOT . 'App/Helpers/' . str_replace('.', '/', strtolower($name)) . '.php',
            ],
            'facade' => [
                ROOT . 'App/Templates/Facade.ghost.tpl',
                ROOT . 'App/Facades/' . ucfirst(str_replace('.', '/', strtolower($name))) . '.php',
            ],
            default => ['', ''],
        };
    }

    private function generateFile(string $template, string $destination, string $type, string $name): void
    {
        $ghost = new Template($template);
        $ghost->set('type', $type);
        $ghost->set('name', $name);

        file_put_contents($destination, $ghost->render());

        if ($type === 'controller') {
            $this->fixRoutes($name);
        }

        $this->success("{$type} {$name} successfully created. ({$destination})");
    }

    private function generateView(string $destination, string $type, string $name): void
    {
        $content = <<<'TPL'
        {{ extends('layouts.master') }}

        #set[content]
            <p>Welcome to <b> @routeName()</b> page</p>
            <p>This file you can find in App/Views/@routeName().ghost.tpl</p>
            <p>Also check out  App/Views/layouts/master.ghost.tpl<p>
        #end
        TPL;

        file_put_contents($destination, $content);
        $this->success("{$type} {$name} successfully created. ({$destination})");
    }

    private function fixRoutes(string $name, bool $add = true): void
    {
        $name = ucfirst($name);

        $routesPath = is_file(ROOT . 'App/Routes.php')
            ? ROOT . 'App/Routes.php'
            : ROOT . 'App/Routes/Web.php';

        $content = file($routesPath);
        $fh = fopen($routesPath, 'w');

        if (!$fh) {
            $this->error("Cannot open {$routesPath}.");
            return;
        }

        foreach ($content as $row) {
            if (str_contains($row, "{$name}@") && !str_starts_with(trim($row), '//')) {
                fwrite($fh, '// ' . trim($row) . "    -- removed by resource manager\n");
            } else {
                fwrite($fh, $row);
            }
        }

        if ($add) {
            fwrite($fh, "\n// Route for {$name} controller.\n");
            fwrite($fh, "Route::get('/" . lcfirst($name) . "', '{$name}@get', '" . lcfirst($name) . "', 'public');\n");
            $this->success('Route created!');
        }

        fclose($fh);
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
