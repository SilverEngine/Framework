<?php
declare(strict_types=1);

namespace Silver\Support;

final class Log
{
    private const array TYPES = [
        'info', 'ok', 'warning', 'error', 'api', 'db',
        'start', 'end', 'debug', 'normal', 'danger',
        'aboard', 'finish', 'url',
    ];

    public function __call(string $method, array $args): void
    {
        if (!in_array($method, self::TYPES, true)) {
            throw new \Exception(
                "Undefined method Log::$method. Allowed: " . implode(', ', self::TYPES),
            );
        }
        $this->create($args[0] ?? '', $method);
    }

    private function create(string $message, string $type): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $path = ROOT . 'Storage/Logs/' . date('Y-m-d') . '.log';

        $fp = fopen($path, 'a+');
        if (!$fp) {
            throw new \Exception("Unable to write to file $path.");
        }

        $line = '[' . date('Y-m-d H:i:s') . '][ ' . $type . " ]\t$ip\t$message\r\n";
        fwrite($fp, $line);
        fclose($fp);
    }
}
