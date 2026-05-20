<?php
declare(strict_types=1);

namespace Silver\Exception;

class Exception extends \Exception
{
    public function __construct(?string $message = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message ?? '', $code, $previous);
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    public function setLine(int $line): void
    {
        $this->line = $line;
    }
}
