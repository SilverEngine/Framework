<?php
declare(strict_types=1);

namespace Silver\Orm\Connection;

use RuntimeException;
use Throwable;

/**
 * Per-connection nested-transaction counter. Level 1 uses real
 * BEGIN/COMMIT/ROLLBACK; deeper nesting uses SAVEPOINT LEVELn /
 * RELEASE / ROLLBACK TO. Counters are keyed by connection name so
 * concurrent transactions on different connections don't collide.
 */
class TransactionManager
{
    /** @var array<string, int> keyed by connection name */
    private array $counter = [];

    public function __construct(
        private readonly ConnectionManager $connections,
    ) {}

    public function level(?string $name = null): int
    {
        $name ??= $this->connections->defaultName();
        return $this->counter[$name] ?? 0;
    }

    public function begin(?string $name = null): void
    {
        $name ??= $this->connections->defaultName();
        $pdo   = $this->connections->pdo($name);
        $level = $this->bump($name, 1);

        if ($level === 1) {
            $pdo->beginTransaction();
        } else {
            $this->connections->exec("SAVEPOINT LEVEL{$level}", $name);
        }
    }

    public function commit(?string $name = null): void
    {
        $name ??= $this->connections->defaultName();
        $pdo   = $this->connections->pdo($name);
        $level = $this->bump($name, -1) + 1;

        if ($level < 1) {
            throw new RuntimeException('There is no active transaction.');
        }
        if ($level === 1) {
            $pdo->commit();
        } else {
            $this->connections->exec("RELEASE SAVEPOINT LEVEL{$level}", $name);
        }
    }

    public function rollBack(?string $name = null): void
    {
        $name ??= $this->connections->defaultName();
        $pdo   = $this->connections->pdo($name);
        $level = $this->bump($name, -1) + 1;

        if ($level < 1) {
            throw new RuntimeException('There is no active transaction.');
        }
        if ($level === 1) {
            $pdo->rollBack();
        } else {
            $this->connections->exec("ROLLBACK TO SAVEPOINT LEVEL{$level}", $name);
        }
    }

    /**
     * Run $cb inside a transaction. Auto begin/commit; rollback + rethrow
     * on exception. Returns whatever $cb returns.
     *
     * The legacy $suppress flag turns the rethrow into a `false` return.
     */
    public function run(callable $cb, bool $suppress = false, ?string $name = null): mixed
    {
        try {
            $this->begin($name);
            $result = $cb();
            $this->commit($name);
            return $result;
        } catch (Throwable $e) {
            $this->rollBack($name);
            if ($suppress) {
                return false;
            }
            throw $e;
        }
    }

    private function bump(string $name, int $delta): int
    {
        $current = $this->counter[$name] ?? 0;
        return $this->counter[$name] = $current + $delta;
    }
}
