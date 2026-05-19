<?php
declare(strict_types=1);

namespace Silver\Core\Contracts;

interface InstanceInterface
{
    public static function instance(): static;
}
