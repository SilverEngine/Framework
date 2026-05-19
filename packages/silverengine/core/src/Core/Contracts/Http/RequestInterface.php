<?php
declare(strict_types=1);

namespace Silver\Core\Contracts\Http;

interface RequestInterface
{
    public function getUri(): ?string;
    public function method(): string;
    public function header(): array;
}
