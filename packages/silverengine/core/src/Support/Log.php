<?php
declare(strict_types=1);

namespace Silver\Support;

final class Log
{
    public function __call(string $method, array $args): void
    {
        $type = LogType::tryFrom($method);
        if ($type === null) {
            throw new \Exception(
                "Undefined method Log::$method. Allowed: " . LogType::names(),
            );
        }
        $this->create($args[0] ?? '', $type);
    }

    private function create(string $message, LogType $type): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $path = ROOT . 'storage/Logs/' . date('Y-m-d') . '.log';

        $fp = fopen($path, 'a+');
        if (!$fp) {
            throw new \Exception("Unable to write to file $path.");
        }

        $line = '[' . date('Y-m-d H:i:s') . '][ ' . $type->value . " ]\t$ip\t$message\r\n";
        fwrite($fp, $line);
        fclose($fp);
    }
}
