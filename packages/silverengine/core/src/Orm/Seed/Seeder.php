<?php
declare(strict_types=1);

namespace Silver\Orm\Seed;

/**
 * Base class for database seeders. Seed files live in
 * `database/seeds/*.php` and — like migrations — return an instance:
 *
 *     <?php
 *     use Silver\Orm\Seed\Seeder;
 *
 *     return new class extends Seeder {
 *         public function run(): void
 *         {
 *             User::create([...]);
 *         }
 *     };
 *
 * `php silver db:seed` (or `migrate:fresh --seed`) executes every
 * file in filename order — prefix with numbers to control sequence.
 */
abstract class Seeder
{
    abstract public function run(): void;
}
