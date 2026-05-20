<?php

declare(strict_types=1);

namespace Silver\Database;

/**
 * Owns the nested-transaction logic: a per-connection depth counter
 * with real BEGIN/COMMIT/ROLLBACK at level 1 and SAVEPOINT LEVELn for
 * deeper nesting.
 *
 * Resolved as a singleton through the container. The counter is keyed
 * by the active default connection name (via
 * {@see ConnectionManager::defaultName()}). Savepoint SQL is routed
 * through `Db::exec()` so the debug echo behaviour is preserved 1:1.
 * `commit()` is intentionally return-type-free — `run()` does
 * `return $this->commit()` and the legacy contract returns null there.
 */
final class TransactionManager
{
    /** @var array<string,int> keyed by default connection name */
    private array $counter = [];

    public function __construct(
        private readonly ConnectionManager $connections,
    ) {}

    public function level(): int
    {
        $db = $this->connections->defaultName();

        if (!isset($this->counter[$db])) {
            $this->counter[$db] = 0;
        }

        return $this->counter[$db];
    }

    private function set(int $num): int
    {
        $db = $this->connections->defaultName();

        return $this->counter[$db] = $num;
    }

    private function inc(int $delta = 1): int
    {
        $num = $this->level();
        $this->set($num + $delta);

        return $num + $delta;
    }

    public function begin(): void
    {
        $conn = $this->connections->pdo();
        $level = $this->inc();

        if ($level == 1) {
            $conn->beginTransaction();
        } else {
            Db::exec('SAVEPOINT LEVEL' . $level);
        }
    }

    /** @throws \Exception */
    public function commit()
    {
        $conn = $this->connections->pdo();
        $level = $this->inc(-1) + 1;

        if ($level < 1) {
            throw new \Exception("There is no active transaction.");
        } elseif ($level == 1) {
            $conn->commit();
        } else {
            Db::exec('RELEASE SAVEPOINT LEVEL' . $level);
        }
    }

    /** @throws \Exception */
    public function rollBack(): void
    {
        $conn = $this->connections->pdo();
        $level = $this->inc(-1) + 1;

        if ($level < 1) {
            throw new \Exception("There is no active transaction.");
        } elseif ($level == 1) {
            $conn->rollBack();
        } else {
            Db::exec('ROLLBACK TO SAVEPOINT LEVEL' . $level);
        }
    }

    /**
     * @param bool $suppress
     * @return bool|void
     * @throws \Exception
     */
    public function run($cb, $suppress = false)
    {
        try {
            $this->begin();
            $cb();

            return $this->commit();
        } catch (\Exception $e) {
            $this->rollBack();
            if ($suppress) {
                return false;
            } else {
                throw $e;
            }
        }
    }
}
