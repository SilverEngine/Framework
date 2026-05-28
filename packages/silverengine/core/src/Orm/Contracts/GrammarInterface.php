<?php
declare(strict_types=1);

namespace Silver\Orm\Contracts;

use Silver\Orm\Connection\Driver;

/**
 * Compiles a Query\QueryState into [sql, bindings] for a specific driver.
 *
 * Implementations are pure: same state in, same SQL out. No side effects,
 * no PDO contact, no model awareness.
 */
interface GrammarInterface
{
    public function driver(): Driver;

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function compileSelect(object $state): array;

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function compileInsert(string $table, array $rows): array;

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function compileUpdate(object $state, array $values): array;

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function compileDelete(object $state): array;

    public function explainPrefix(): string;

    public function analyzePrefix(): string;
}
