<?php
declare(strict_types=1);

namespace Silver\Init;

final class Silver
{
    public function run(): void
    {
        Image::pull();
    }
}
