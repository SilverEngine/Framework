<?php
declare(strict_types=1);

namespace System\App\Controllers;

use Silver\Core\Controller;
use Silver\Database\Query as DB;

class MigrationsController extends Controller
{
    public function up(string|false $modelName = false): string
    {
        $list = [];

        if ($modelName) {
            if ($modelName === 'migrations') {
                return 'This is GLOBAL SYSTEM Name!';
            }

            $filePath = ROOT . 'Database' . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR . $modelName . 'Migrate.php';
            if (!is_file($filePath)) {
                return "File not found: {$filePath}";
            }

            $migrate = "Database\\Migrations\\" . ucfirst($modelName) . 'Migrate';
            $migrate::up();
            $list[] = $modelName;

            $model = ucfirst($modelName) . 'Migrate';
            $check = DB::count()->from('migrations')->where('model_name', $model)->fetch();

            if ($check->count == 0) {
                DB::insert('migrations', ['model_name' => $model])->execute();
            }
        } else {
            $path = ROOT . 'Database/Migrations/';
            $files = array_diff(scandir($path), ['.', '..']);

            foreach ($files as $row) {
                $row = preg_replace('/\.php$/', '', $row);

                if ($row !== 'Migrations') {
                    DB::insert('Migrations', ['model_name' => $row])->execute();
                }

                $migrate = "Database\\Migrations\\" . ucfirst($row);
                $migrate::up();
                $list[] = $row;
            }
        }

        return implode(', ', $list) . ' - Migrations created!';
    }

    public function down(string|false $modelName = false): string
    {
        $list = [];

        if ($modelName) {
            if ($modelName === 'migrations') {
                return 'This is GLOBAL SYSTEM Name!';
            }

            $filePath = ROOT . 'Database' . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR . $modelName . 'Migrate.php';
            if (!is_file($filePath)) {
                return "File not found: {$filePath}";
            }

            $migrate = "Database\\Migrations\\" . ucfirst($modelName) . 'Migrate';
            $migrate::down();
            $list[] = $modelName;
        } else {
            $path = ROOT . 'Database/Migrations/';
            $files = array_diff(scandir($path), ['.', '..']);

            foreach ($files as $row) {
                $row = preg_replace('/\.php$/', '', $row);
                $migrate = "Database\\Migrations\\" . ucfirst($row);
                $migrate::down();
                $list[] = $row;
            }
        }

        return implode(', ', $list) . ' - Migrations dropped!';
    }

    public function all(): string
    {
        $list = [];
        $listSeeds = [];

        $path = ROOT . 'Database/Migrations/';
        foreach (array_diff(scandir($path), ['.', '..']) as $row) {
            $row = preg_replace('/\.php$/', '', $row);
            $migrate = "Database\\Migrations\\" . ucfirst($row);
            $migrate::up();
            $list[] = $row;
        }

        $seedPath = ROOT . 'Database/Seeds/';
        if (is_dir($seedPath)) {
            foreach (array_diff(scandir($seedPath), ['.', '..']) as $row) {
                $row = preg_replace('/\.php$/', '', $row);
                $seed = "Database\\Seeds\\" . ucfirst($row);
                $seed::run();
                $listSeeds[] = $row;
            }
        }

        return implode(', ', $list) . ' | Seeds: ' . implode(', ', $listSeeds) . ' - Complete!';
    }
}
