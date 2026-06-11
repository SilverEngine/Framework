<?php
declare(strict_types=1);

namespace Silver\Orm\Seed;

/**
 * Executes every seeder file in a directory, in filename order.
 * Each file must `return` a {@see Seeder} instance.
 */
final class SeedRunner
{
    /** @return list<string> basenames of the executed seed files */
    public function run(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $seeder = require $file;
            if (!$seeder instanceof Seeder) {
                throw new \RuntimeException(
                    basename($file) . ' must return a Silver\Orm\Seed\Seeder instance.',
                );
            }
            $seeder->run();
            $ran[] = basename($file);
        }
        return $ran;
    }
}
