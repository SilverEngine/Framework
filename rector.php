<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

/**
 * Scoped to the new ORM tree + app code while the legacy codebase
 * stabilises. The default `composer rector` runs --dry-run so a drift
 * fails the build without auto-modifying; `composer rector:apply`
 * actually rewrites. Pair with `composer check` (test + stan + rector).
 *
 * The PHP set is pinned to PHP 8.4 for now — the project declares
 * 8.5+ but rector 2.x's PHP 8.5 set is conservative and we'd rather
 * call out 8.5-specific rewrites in their own PR.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages/silverengine/core/src/Orm',
        __DIR__ . '/app',
    ])
    ->withSkip([
        // Test fixtures are intentionally minimal — Rector would push
        // them toward types/promotion that obscure what the test under
        // test is asserting.
        __DIR__ . '/tests/Unit/Framework/Orm/Model/fixtures',
        __DIR__ . '/tests/Unit/Framework/Orm/Relations/fixtures',
        __DIR__ . '/tests/Unit/Framework/Orm/Schema/fixtures',
        // Pre-existing syntax bug from a prior scaffolder run —
        // tracked separately. Skipping lets Rector run cleanly across
        // the rest of app/.
        __DIR__ . '/app/Controllers/TestErrorController.php',
    ])
    ->withImportNames(importShortClasses: false)
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ]);
