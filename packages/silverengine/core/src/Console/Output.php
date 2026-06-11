<?php
declare(strict_types=1);

namespace Silver\Console;

/**
 * Console output. Normal text goes to stdout, errors to stderr; tests
 * pass in-memory streams to capture both.
 */
final class Output
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct($stdout = null, $stderr = null)
    {
        $this->stdout = $stdout ?? \STDOUT;
        $this->stderr = $stderr ?? \STDERR;
    }

    public function line(string $message = ''): void
    {
        fwrite($this->stdout, $message . "\n");
    }

    public function info(string $message): void
    {
        $this->line($message);
    }

    public function success(string $message): void
    {
        $this->line($message);
    }

    public function error(string $message): void
    {
        fwrite($this->stderr, "ERROR: {$message}\n");
    }

    /**
     * Render rows as a padded table. Column widths come from content.
     *
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen($cell));
            }
        }

        $render = function (array $cells) use ($widths): string {
            $out = [];
            foreach ($cells as $i => $cell) {
                $out[] = str_pad($cell, $widths[$i]);
            }
            return rtrim(implode('  ', $out));
        };

        $this->line($render($headers));
        $this->line($render(array_map(fn (int $w) => str_repeat('-', $w), $widths)));
        foreach ($rows as $row) {
            $this->line($render($row));
        }
    }
}
