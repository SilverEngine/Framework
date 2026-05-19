<?php

declare(strict_types=1);

namespace Silver\Support;

use Silver\Core\Env;

/**
 * Persists each request's full lifecycle (the {@see DebugTimer}
 * timeline + summary) to storage/debug/recordings/ so it can be
 * reviewed later from /debug?tab=recordings.
 *
 * Recording runs at the very end of Kernel::run() — after the response
 * is sent — so it captures the phases the live /debug page cannot show
 * about itself (controller action, view render, response sent).
 *
 * Capture is record-all, gated by APP debug + `recorder.enabled`, with
 * a `recorder.ignore` path-prefix filter and a `recorder.limit` ring
 * buffer. All filesystem work is best-effort: the response is already
 * sent, so the recorder must never raise.
 */
final class RequestRecorder
{
    public static function dir(): string
    {
        return (defined('ROOT') ? ROOT : '') . 'storage/debug/recordings/';
    }

    public static function record(string $method, string $path, int $status): void
    {
        try {
            if (!Env::get('debug') || !Env::get('recorder.enabled', true)) {
                return;
            }

            foreach ((array) Env::get('recorder.ignore', []) as $prefix) {
                if ($prefix !== '' && str_starts_with($path, (string) $prefix)) {
                    return;
                }
            }

            $dir = self::dir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return;
            }

            $id = sprintf('%013d-%s', (int) (microtime(true) * 1000), bin2hex(random_bytes(3)));

            $files = DebugTimer::files();
            $payload = [
                'id'          => $id,
                'at'          => date('Y-m-d H:i:s'),
                'method'      => $method,
                'path'        => $path,
                'status'      => $status,
                'total_ms'    => DebugTimer::totalMs(),
                'mem_peak_kb' => round(memory_get_peak_usage(true) / 1024, 1),
                'files_count' => count($files),
                'files'       => $files,
                'timeline'    => DebugTimer::timeline(),
            ];

            @file_put_contents(
                $dir . $id . '.json',
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                LOCK_EX,
            );

            self::prune((int) Env::get('recorder.limit', 50));
        } catch (\Throwable) {
            // best-effort: never disrupt a request that already responded
        }
    }

    /** Newest-first list of recording summaries (no timeline payload). */
    public static function all(): array
    {
        $out = [];
        foreach (self::sortedFiles() as $file) {
            $data = json_decode((string) @file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            unset($data['timeline'], $data['files']);
            $out[] = $data;
        }
        return array_reverse($out);
    }

    public static function find(string $id): ?array
    {
        if (!preg_match('/^[0-9a-f-]+$/', $id)) {
            return null;
        }
        $path = self::dir() . $id . '.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /** @return list<string> ascending (oldest first) by sortable id */
    private static function sortedFiles(): array
    {
        $files = glob(self::dir() . '*.json') ?: [];
        sort($files);
        return $files;
    }

    private static function prune(int $limit): void
    {
        $files = self::sortedFiles();
        $excess = count($files) - max(1, $limit);
        for ($i = 0; $i < $excess; $i++) {
            @unlink($files[$i]);
        }
    }
}
