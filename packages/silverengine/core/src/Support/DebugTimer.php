<?php
declare(strict_types=1);

namespace Silver\Support;

/**
 * Per-request timeline recorder. Resolved as a singleton through the
 * container; reached most often via the global `dt()` helper so call
 * sites stay compact (`dt()->begin(...)`, `dt()->mark(...)`).
 *
 * Methods are no-ops until {@see self::start()} has been called, so the
 * timer can be wired into hot paths and only switched on by the boot
 * code that wants the data.
 */
final class DebugTimer
{
    private bool $enabled = false;
    private int $origin = 0;
    private array $events = [];
    private array $openSpans = [];

    public function start(): void
    {
        if ($this->enabled) {
            return;
        }
        $this->enabled = true;
        $this->origin = defined('APP_START') ? APP_START : hrtime(true);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function mark(string $label, ?string $category = null): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->events[] = [
            'type'     => 'mark',
            'label'    => $label,
            'category' => $category ?? 'app',
            'time'     => hrtime(true),
        ];
    }

    public function begin(string $label, ?string $category = null): void
    {
        if (!$this->enabled) {
            return;
        }
        $key = $category . '::' . $label;
        $this->openSpans[$key] = hrtime(true);
    }

    public function end(string $label, ?string $category = null): void
    {
        if (!$this->enabled) {
            return;
        }
        $key = $category . '::' . $label;
        $start = $this->openSpans[$key] ?? null;
        if ($start === null) {
            return;
        }
        unset($this->openSpans[$key]);
        $this->events[] = [
            'type'     => 'span',
            'label'    => $label,
            'category' => $category ?? 'app',
            'start'    => $start,
            'end'      => hrtime(true),
        ];
    }

    /**
     * Inject a closed span retroactively. Use when a phase ran *before*
     * {@see start()} was even called — most commonly the autoload +
     * bootstrap window between APP_START and the first dt() activity.
     * Counterpart to {@see begin()}/{@see end()} for code paths where you
     * already have both hrtime() values.
     */
    public function record(string $label, string $category, int $startNs, int $endNs): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->events[] = [
            'type'     => 'span',
            'label'    => $label,
            'category' => $category,
            'start'    => $startNs,
            'end'      => $endNs,
        ];
    }

    /**
     * Render the timeline. When $includeOpen is true (default), spans that
     * have called begin() but not yet end() — typically the middleware
     * frames and controller action wrapping the caller — are reported with
     * end_ms = now and `in_progress => true`, so observers fired mid-request
     * (like /heartbeat) still see them on the waterfall.
     */
    public function timeline(bool $includeOpen = true): array
    {
        if (!$this->enabled) {
            return [];
        }
        $origin = $this->origin;
        $timeline = [];
        foreach ($this->events as $e) {
            if ($e['type'] === 'mark') {
                $timeline[] = [
                    'type'     => 'mark',
                    'label'    => $e['label'],
                    'category' => $e['category'],
                    'at_ms'    => ($e['time'] - $origin) / 1e6,
                ];
            } else {
                $timeline[] = [
                    'type'        => 'span',
                    'label'       => $e['label'],
                    'category'    => $e['category'],
                    'start_ms'    => ($e['start'] - $origin) / 1e6,
                    'end_ms'      => ($e['end'] - $origin) / 1e6,
                    'duration_ms' => ($e['end'] - $e['start']) / 1e6,
                    'in_progress' => false,
                ];
            }
        }

        if ($includeOpen && $this->openSpans !== []) {
            $now = hrtime(true);
            foreach ($this->openSpans as $key => $start) {
                // Key is "{category}::{label}".
                [$category, $label] = explode('::', $key, 2) + ['app', $key];
                $timeline[] = [
                    'type'        => 'span',
                    'label'       => $label,
                    'category'    => $category,
                    'start_ms'    => ($start - $origin) / 1e6,
                    'end_ms'      => ($now - $origin) / 1e6,
                    'duration_ms' => ($now - $start) / 1e6,
                    'in_progress' => true,
                ];
            }
        }

        return $timeline;
    }

    public function files(): array
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

    public function totalMs(): float
    {
        if (!$this->enabled) {
            return 0.0;
        }
        return (hrtime(true) - $this->origin) / 1e6;
    }
}
