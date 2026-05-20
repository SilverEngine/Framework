<?php
declare(strict_types=1);

namespace Silver\Support;

final class DebugTimer
{
    private static ?self $instance = null;
    private int $origin;
    private array $events = [];
    private array $openSpans = [];

    private function __construct()
    {
        $this->origin = defined('APP_START') ? APP_START : hrtime(true);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function enabled(): bool
    {
        return self::$instance !== null;
    }

    public static function start(): void
    {
        self::instance();
    }

    public static function mark(string $label, ?string $category = null): void
    {
        if (!self::$instance) {
            return;
        }

        self::$instance->events[] = [
            'type'     => 'mark',
            'label'    => $label,
            'category' => $category ?? 'app',
            'time'     => hrtime(true),
        ];
    }

    public static function begin(string $label, ?string $category = null): void
    {
        if (!self::$instance) {
            return;
        }

        $key = $category . '::' . $label;
        self::$instance->openSpans[$key] = hrtime(true);
    }

    public static function end(string $label, ?string $category = null): void
    {
        if (!self::$instance) {
            return;
        }

        $key = $category . '::' . $label;
        $start = self::$instance->openSpans[$key] ?? null;
        if ($start === null) {
            return;
        }

        unset(self::$instance->openSpans[$key]);

        self::$instance->events[] = [
            'type'     => 'span',
            'label'    => $label,
            'category' => $category ?? 'app',
            'start'    => $start,
            'end'      => hrtime(true),
        ];
    }

    public static function timeline(): array
    {
        if (!self::$instance) {
            return [];
        }

        $origin = self::$instance->origin;
        $timeline = [];

        foreach (self::$instance->events as $e) {
            if ($e['type'] === 'mark') {
                $timeline[] = [
                    'type'     => 'mark',
                    'label'    => $e['label'],
                    'category' => $e['category'],
                    'at_ms'    => ($e['time'] - $origin) / 1e6,
                ];
            } else {
                $timeline[] = [
                    'type'       => 'span',
                    'label'      => $e['label'],
                    'category'   => $e['category'],
                    'start_ms'   => ($e['start'] - $origin) / 1e6,
                    'end_ms'     => ($e['end'] - $origin) / 1e6,
                    'duration_ms'=> ($e['end'] - $e['start']) / 1e6,
                ];
            }
        }

        return $timeline;
    }

    public static function files(): array
    {
        $files = get_included_files();
        $root = defined('ROOT') ? ROOT : '';
        $result = [];

        foreach ($files as $file) {
            $relative = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
            $size = is_file($file) ? filesize($file) : 0;
            $result[] = [
                'path' => $relative,
                'size' => $size,
            ];
        }

        return $result;
    }

    public static function totalMs(): float
    {
        if (!self::$instance) {
            return 0.0;
        }
        return (hrtime(true) - self::$instance->origin) / 1e6;
    }
}
