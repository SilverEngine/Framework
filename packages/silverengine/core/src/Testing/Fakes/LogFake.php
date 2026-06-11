<?php
declare(strict_types=1);

namespace Silver\Testing\Fakes;

use PHPUnit\Framework\Assert;
use Silver\Support\LogType;

/**
 * In-memory stand-in for {@see \Silver\Support\Log} — same magic-method
 * surface (one method per {@see LogType} case), but records lines
 * instead of appending to storage/Logs. Swap in via
 * {@see \Silver\Testing\TestCase::fakeLog()}.
 */
final class LogFake
{
    /** @var list<array{type: string, message: string}> */
    private array $records = [];

    /** @param array<int, mixed> $args */
    public function __call(string $method, array $args): void
    {
        $type = LogType::tryFrom($method)
            ?? throw new \Exception(
                "Undefined method Log::$method. Allowed: " . LogType::names(),
            );

        $this->records[] = ['type' => $type->value, 'message' => (string) ($args[0] ?? '')];
    }

    /** @return list<array{type: string, message: string}> everything recorded, in order */
    public function lines(): array
    {
        return $this->records;
    }

    /** @return list<string> the messages recorded at $type */
    public function logged(string $type): array
    {
        return array_values(array_map(
            static fn (array $r): string => $r['message'],
            array_filter($this->records, static fn (array $r): bool => $r['type'] === $type),
        ));
    }

    /** Assert something was logged at $type, optionally containing $needle. */
    public function assertLogged(string $type, ?string $needle = null): void
    {
        $messages = $this->logged($type);
        Assert::assertNotEmpty($messages, "Nothing was logged at level [{$type}].");

        if ($needle !== null) {
            Assert::assertTrue(
                array_any($messages, static fn (string $m): bool => str_contains($m, $needle)),
                "No [{$type}] log line contains \"{$needle}\". Got: " . implode(' | ', $messages),
            );
        }
    }

    public function assertNothingLogged(): void
    {
        Assert::assertSame([], $this->records, 'Expected no log lines, but some were recorded.');
    }
}
