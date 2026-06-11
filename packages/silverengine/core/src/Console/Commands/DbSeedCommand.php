<?php
declare(strict_types=1);

namespace Silver\Console\Commands;

use Silver\Console\Contracts\Command;
use Silver\Console\Input;
use Silver\Console\Output;
use Silver\Core\RuntimeContext;
use Silver\Orm\Model\Model;
use Silver\Orm\Schema\DatabaseBootstrap;
use Silver\Orm\Seed\SeedRunner;

/**
 * `php silver db:seed` — run every seeder in database/seeds
 * (filename order). Binds the ORM first so seeders can use models.
 */
final class DbSeedCommand implements Command
{
    public function description(): string
    {
        return 'Run the database seeders in database/seeds';
    }

    public function handle(Input $input, Output $output): int
    {
        $root = RuntimeContext::current()->root;

        [$cm] = DatabaseBootstrap::build($root);
        Model::bind($cm);

        try {
            $ran = new SeedRunner()->run($root . 'database/seeds');
        } catch (\Throwable $e) {
            $output->error($e->getMessage());
            return 1;
        }

        if ($ran === []) {
            $output->info('Nothing to seed (database/seeds is empty or missing).');
            return 0;
        }

        foreach ($ran as $file) {
            $output->success("  Seeded  {$file}");
        }
        return 0;
    }
}
