<?php
declare(strict_types=1);

namespace Silver\Core\Contracts\Database;

interface MigrationInterface
{
    public function create(): void;
    public function drop(): void;
    public function test(): void;
    public function seed(): void;
}
