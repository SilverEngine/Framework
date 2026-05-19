<?php
declare(strict_types=1);

namespace Silver\Core\Bootstrap;

interface ServiceProvider
{
    public function before(mixed $kernel): void;
    public function register(mixed $app): void;
    public function after(): void;
}
