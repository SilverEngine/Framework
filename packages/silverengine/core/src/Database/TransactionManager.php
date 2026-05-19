<?php

declare(strict_types=1);

namespace Silver\Database;

/**
 * Owns the nested-transaction logic extracted from the Db God class:
 * a per-connection depth counter with real BEGIN/COMMIT/ROLLBACK at
 * level 1 and SAVEPOINT LEVELn for deeper nesting.
 *
 * The counter is keyed by the active default connection name (via
 * {@see ConnectionManager::defaultName()}) exactly as the old
 * `Db::$tx_counter` was. Savepoint SQL is routed through `Db::exec()`
 * so the debug echo behaviour is preserved 1:1. `commit()` is
 * intentionally return-type-free — `run()` does `return self::commit()`
 * and the legacy contract returns null there.
 */
final class TransactionManager
{
    /** @var array<string,int> keyed by default connection name */
    private static array $counter = [];

    public static function level(): int
    {
        $db = ConnectionManager::defaultName();

        if (!isset(self::$counter[$db])) {
            self::$counter[$db] = 0;
        }

        return self::$counter[$db];
    }

    private static function set(int $num): int
    {
        $db = ConnectionManager::defaultName();

        return self::$counter[$db] = $num;
    }

    private static function inc(int $delta = 1): int
    {
        $num = self::level();
        self::set($num + $delta);

        return $num + $delta;
    }

    public static function begin(): void
    {
        $conn = ConnectionManager::pdo();
        $level = self::inc();

        if ($level == 1) {
            $conn->beginTransaction();
        } else {
            Db::exec('SAVEPOINT LEVEL' . $level);
        }
    }

    /** @throws \Exception */
    public static function commit()
    {
        $conn = ConnectionManager::pdo();
        $level = self::inc(-1) + 1;

        if ($level < 1) {
            throw new \Exception("There is no active transaction.");
        } elseif ($level == 1) {
            $conn->commit();
        } else {
            Db::exec('RELEASE SAVEPOINT LEVEL' . $level);
        }
    }

    /** @throws \Exception */
    public static function rollBack(): void
    {
        $conn = ConnectionManager::pdo();
        $level = self::inc(-1) + 1;

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
    public static function run($cb, $suppress = false)
    {
        try {
            self::begin();
            $cb();

            return self::commit();
        } catch (\Exception $e) {
            self::rollBack();
            if ($suppress) {
                return false;
            } else {
                throw $e;
            }
        }
    }
}
