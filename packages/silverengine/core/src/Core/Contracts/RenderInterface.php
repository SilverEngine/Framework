<?php
declare(strict_types=1);

namespace Silver\Core\Contracts;

interface RenderInterface
{
    public function render(): string;
    public function data(): array;
}
