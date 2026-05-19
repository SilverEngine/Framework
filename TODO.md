# SilverEngine Core Package Migration

## Goal

Extract `System/` into a standalone Composer package `silverengine/core` living in
`Packages/silverengine/core/`, the same way `silverengine/error-handler` already works.
Modernise every migrated file to PHP 8.4+ idioms as it moves.

**Strategy:** incremental migration — move files to package, modernize to PHP 8.4+,
delete from `System/`. Unmigrated files stay in `System/` via root PSR-4 fallback.
Framework stays bootable at every step.

**Dependencies added:** `vlucas/phpdotenv`, `nejcc/php-datatypes`

**Naming:** `Blueprints` renamed to `Contracts`, `MigrationCore` to `MigrationInterface`

---

## Phase 0 — Scaffold the package [DONE]

- [x] Create `Packages/silverengine/core/composer.json` (php >=8.4, vlucas/phpdotenv)
- [x] Create `Packages/silverengine/core/src/` directory tree
- [x] Add `silverengine/core` + `nejcc/php-datatypes` as deps in root `composer.json`
- [x] Run `composer update` — autoload resolves from package
- [x] Root `Silver\\` PSR-4 kept as fallback for unmigrated files

---

## Phase 1 — Core kernel [DONE]

- [x] `Core/Env.php` — rewritten for vlucas/phpdotenv, typed properties
- [x] `Core/Config.php` — typed returns
- [x] `Core/App.php` — typed properties, implements `InstanceInterface`
- [x] `Core/Instances.php` — fully typed container
- [x] `Core/DI.php` — `ReflectionNamedType` (replaces deprecated `getClass()`)
- [x] `Core/Kernel.php` — `never` return, arrow fn middleware chain
- [x] `Core/Route.php` — `str_starts_with`/`str_ends_with`, throw expressions
- [x] `Core/Controller.php` — declared `$controllerName`, fixed types
- [x] `Core/Model.php` — declared all subclass properties, removed unused
- [x] `Core/Library.php` — typed params, `never` return on `dd()`
- [x] `Core/ErrorHandler.php` — handles `\Throwable`, `never` on `finalize()`
- [x] `Core/Bootstrap.php` — `readonly` property
- [x] `Core/AppInstanceTrait.php` — `static` return type
- [x] `Core/helpers.php` — `env()` helper with `match` expression
- [x] Old `System/Core/` files deleted, package loads confirmed
- [x] `.env` file created (replaces `local.env.php` approach)
- [x] `public/index.php` rewritten (dotenv, first-class callables for error handlers)
- [x] `silver` CLI entry point modernized

---

## Phase 2 — Contracts (was Blueprints) [DONE]

- [x] `Core/Contracts/InstanceInterface.php` — `static` return type
- [x] `Core/Contracts/MiddlewareInterface.php` — `: mixed` return
- [x] `Core/Contracts/RenderInterface.php` — `: string`, `: array` returns
- [x] `Core/Contracts/Http/RequestInterface.php` — typed returns
- [x] `Core/Contracts/Http/ResponseInterface.php`
- [x] `Core/Contracts/Database/MigrationInterface.php` — renamed from `MigrationCore`
- [x] All `System/` and `App/` implementors updated to use `Contracts` namespace
- [x] Old `System/Core/Blueprints/` deleted

---

## Phase 3 — Bootstrap & Facades [DONE]

- [x] `Core/Bootstrap/Autoload.php` — `str_starts_with()`
- [x] `Core/Bootstrap/ServiceProvider.php` — typed params
- [x] `Core/Bootstrap/Facades/Request.php` — `final`, typed return
- [x] `Core/Bootstrap/Facades/Response.php` — `final`, typed return
- [x] `Core/Bootstrap/Facades/Log.php` — `final`, typed return
- [x] `Core/Bootstrap/Facades/FakeFactory.php` — `final`, typed return
- [x] `Core/Storage/Cache.php` — `final`, typed properties, fixed `$time_or_predictor` bug
- [x] `Core/Http/Lang.php` — `final`, null-safe

---

## Phase 8 — Exceptions [DONE — moved early, no deps]

- [x] `Exception/Exception.php` — `?\Throwable` previous param
- [x] `Exception/ErrorException.php` — `final`
- [x] `Exception/NotFoundException.php` — `final`

---

## `final` keyword applied to [DONE]

Env, Config, DI, Instances, Bootstrap, ErrorHandler, Cache, Lang,
all 4 Facades, ErrorException, NotFoundException

---

## Phase 4 — HTTP layer [DONE]

- [x] `Http/Request.php` — typed properties, `str_starts_with`, `?Route` return
- [x] `Http/Response.php` — nested `match` for content dispatch, `RenderInterface`
- [x] `Http/Session.php` — `final`, removed side-effect auto-call
- [x] `Http/Cookie.php` — `final`, `match` for return types
- [x] `Http/Curl.php` — `final`, removed deprecated `curl_close()`
- [x] `Http/Redirect.php` — `final`, `never` return types
- [x] `Http/Validator.php` — `final`, `string|false` return types
- [x] `Http/View.php` — DRY template extension loop, `str_starts_with`

---

## Phase 5 — Database / Query Builder [DONE]

- [x] 55 files migrated: Db, Query, Model, Compiler, QueryObject, Relation, Source, DBCreator
- [x] Parts/* (20 files), Query/* (10 files), Traits/* (8 files), Source/* (3 files)
- [x] DB-specific variants: Mysql/, Pgsql/, Sqlite/
- [x] `declare(strict_types=1)` added to all files
- [x] Fixed `$self::isDebug()` typo in Db.php
- [x] SQLite connection verified

---

## Phase 6 — Support & Helpers [DONE]

- [x] `Support/Facade.php` — `abstract` base, lazy singleton `??=`
- [x] `Support/Fake.php` — `final`, DRY via `__callStatic` + const array
- [x] `Support/FakeFactory.php` — `const array` data, `random_int()`
- [x] `Support/Log.php` — `final`, typed `const array TYPES`
- [x] `Support/Crypter.php` — `final`, `match` for alphabet, `random_int()`
- [x] `Support/Git.php` — `final`, `readonly` branch property
- [x] `Support/SMail.php` — `final`, typed properties, fluent builder
- [x] `Helpers/Str.php` — renamed from `String` (reserved word), wraps builtins
- [x] `Helpers/Path.php` — `final`, `str_starts_with`
- [x] `Helpers/URL.php` — `str_starts_with`/`str_ends_with`
- [x] `Helpers/HTMLElement.php` — typed constructor, union types

---

## Phase 7 — Engines [DONE]

- [x] `Engine/CLI.php` — `match` expressions, `never` return, DRY `resolvePaths`
- [x] `Engine/Events/EventManager.php` — fixed namespace, typed, removed debug echo
- [x] `Engine/Ghost/Template.php` — DRY `processLines()` helper, arrow fns

---

## Phase 9 — System App (framework defaults) [DONE]

- [x] `App/Controllers/SystemController.php` — typed return
- [x] `App/Controllers/MigrationsController.php` — `string|false` union type
- [x] `App/Middlewares/*` — all `final`, `MiddlewareInterface`, `\Throwable` catch
- [x] `App/Routes.php` — strict comparison
- [x] `App/Views/*` — copied to package
- [x] Config/Routes.php updated to point to package path

---

## Phase 10 — Cleanup & finalize [DONE]

- [x] `System/` directory deleted entirely
- [x] `"Silver\\": "System/"` removed from root `composer.json`
- [x] `Config/Providers.php` — removed stale `Silver => System` mapping
- [x] `Config/Routes.php` — updated system routes path
- [x] Clean boot verified (zero warnings)
- [x] Run full test suite (`Tests/`) — PHPUnit ^12 added as **require-dev**
      (dev-only, runtime stays dependency-light). Suite green:
      14 tests, 19 assertions, 1 skipped (network-only Curl test).
- [x] ~~Update `error-handler` to `php >=8.4`~~ — resolved by removal:
      package absorbed into `silverengine/core`
      (`Silver\ErrorHandler\Reporter`), second path repo dropped
- [ ] Tag `silverengine/core` v0.1.0 — **intentionally deferred** (tag
      step skipped by request; code is tag-ready)

---

## Future Enhancements

- [x] Shared view data — unified `View::share()` store, available in Ghost views AND Wisp pages (`Wisp::share()` delegates to it)
- [x] View composers — `View::composer($pattern, $cb)`, exact + fnmatch wildcard, merged at render via `View::sharedFor()`
- [x] Request improvements — typed `headerValue()`, `hasHeader()`, `query()`, `json()`, `bool()`, `int()`, `wantsJson()`; WispResponse + Wisp middleware refactored off raw `$_SERVER`

### Frontend (Wisp) — DONE

- [x] Vite + Vue 3 + TypeScript + Tailwind 4, official `@inertiajs/vue3` client
- [x] First-party `Silver\Engine\Ghost\{Wisp,WispResponse,Vite,LazyProp,DeferProp}`, `{{ wisp() }}` / `{{ vite() }}` directives, `wisp()` helper
- [x] Inertia wire protocol (X-Inertia headers), version 409 / 303 handshake middleware
- [x] Lazy + deferred props, partial reloads, prefetch-ready `deferredProps`
- [x] `composer dev` runs PHP + Vite concurrently; `composer serve` = PHP only

---

## PHP 8.4+ Refactoring Checklist (apply to every migrated file)

- [x] `declare(strict_types=1);`
- [x] Typed / readonly properties
- [x] Constructor promotion
- [x] `match` instead of `switch`
- [x] `str_starts_with` / `str_ends_with` / `str_contains`
- [x] Union / intersection / nullable types
- [x] `final` on leaf classes
- [x] First-class callable syntax (`$this->method(...)`)
- [x] `never` return type
- [x] Enums for finite value sets — `Silver\Http\HttpMethod`,
      `Silver\Database\DbDriver`, `Silver\Support\LogType`,
      `Silver\Support\PasswordCharset`
- [x] `array_find`, `array_any`, `array_all` (PHP 8.4+) —
      `Response::send()` (array_find), `View::sharedFor()` (array_any);
      remaining loops are accumulation/transform, not idiomatic candidates
- [~] Property hooks where they simplify getters/setters — **no
      behaviour-preserving application in core**: every get/set is
      public method API (converting breaks callers), and the only magic
      accessors (`QueryObject`) are a dynamic property bag hooks can't
      model. Deferred to Phase B (where API changes are in scope).
- [x] Remove remaining legacy phpdoc that duplicates native types —
      Db/Query/Model/DBCreator stripped of zero-info tags; provably-safe
      native return types added; informative phpdoc kept

---

## Phase A — Quality pass (checklist completion) [DONE, untagged]

Mechanical-first, behaviour-preserving. PHPUnit ^12 dev-only baseline.
5 commits. **Flagged for follow-up (Phase B / separate fix):**

- `Database/DBCreator.php` — dead code, zero references; candidate for deletion
- ~~`ColumnDef::compileReference()` `ON UPDATE` spacing bug~~ — **fixed**
  (`fix(db)`); referential actions still an enum candidate for Phase B
- Ambiguous return types left untyped: `Db::{toSql,isDebug,quote,commit,
  transaction,driverName,fetch}`, `QueryObject::__get/__set`

---

## Phase B — Design-pattern audit (full audit, API changes allowed)

Test-first: characterization tests pin observable behaviour BEFORE each
structural change. Per-batch gate (tests → refactor → suite green →
commit). PHPUnit baseline grew 8 → 35 tests, 62 assertions, 1 skipped.

### Done

- [x] **B6 — drop legacy `$_` member prefix.** `Route` private/static
      members `$_foo → $foo` (`$jails`/static→`$jailStack`; PHP forbids
      same-name instance+static). `QueryObject::$_table/$_primary →
      $table/$primaryKey` (typed `?string`, kept `static` — static
      active-record needs table/PK without an instance); migrated
      `App/Models/Users.php`. Tests: `RouteTest`, `QueryObjectTest`.
- [x] **B1 — dialect Strategy.** Extracted `Silver\Database\Dialect`
      (`segment()`/`classFor()`) from the inline `Compiler::toSql()`
      `ucfirst+substr_replace+class_exists`; uses `DbDriver` enum,
      identical fallback. Tests: `DialectCompileTest` (SQL pinned
      pre-refactor), `DialectTest`.
- [x] **B2 — Query factory + `QueryType` enum.** Replaced stringly
      `Query::instance()`; `queryClass()`/`make()` resolve the identical
      FQN. Test: `QueryTypeTest` (enum map + insert/update/delete/drop
      SQL pinned).
- [x] **B3 — CLI `Command` enum.** Replaced `match($this->cmd)`
      literals; alias-aware `parse()` (`c`→Generate, unknown→null).
      Proportionate — no class-per-command framework. Test:
      `CommandTest`.

- [x] **B4 — split the `Db` God class.** Extracted `ConnectionManager`
      (registry/lazy-PDO/raw/exec/quote/lastInsertId/driverName) and
      `TransactionManager` (depth counter + nested BEGIN/COMMIT/ROLLBACK
      + SAVEPOINT levels, savepoint SQL still via `Db::exec()` for the
      debug echo). `Db` is now a thin BC facade keeping only the
      per-instance fetch/debug side; every `Db::`/`Query::`/`Model::`
      static entry point unchanged. Found + fixed a **pre-existing**
      bug first (separate `fix(db)`): `class_exists($style)` before a
      string check threw `TypeError` under `strict_types` — all builder
      result-fetch with default style was broken. Tests: `DbBehaviorTest`
      (10, characterization pinned pre-refactor), `DbFetchTest` (5,
      TDD red→green for the bug). Ambiguous returns
      (`Db::{toSql,isDebug,quote,commit,transaction,driverName,fetch}`)
      left untyped — separate optional polish, see backlog.

### Remaining — large, high-blast-radius (checkpoint: do as focused sessions)

- [ ] **B5 — real IoC container.** Today `Instances` is a registry, not
      a container. Add `bind()` / `singleton()` / interface→impl /
      closure factories / recursive autowiring + constructor injection
      for controllers & middleware. Plan: (1) characterization of the
      current shallow `DI::call` contract (method injection by class
      name + route vars); (2) introduce `Container` (autowiring +
      bindings) keeping `DI::call` semantics as the method-injection
      front end; (3) constructor-inject controllers in
      `Kernel::findCallable` and `new $mw()` in `loadMiddlewares`;
      (4) unify the 3 singleton mechanisms; route `Facade` through the
      container; (5) fix the `ServiceProvider` contract.

### Findings backlog (surfaced during A/B, not silently changed)

- `Database/DBCreator.php` — dead code, zero refs; delete candidate.
- ~~Builder fetch `class_exists(int)` TypeError~~ — **fixed** (`fix(db)`,
  TDD, `DbFetchTest`).
- Ambiguous return types still untyped (optional polish, low value):
  `Db::{toSql,isDebug,quote,commit,transaction,driverName,fetch}`,
  `QueryObject::__get/__set` (dynamic property bag — not a
  property-hook candidate).
- `Route::url()` depends on the global `BASEPATH` constant defined only
  by `public/index.php` — should come via config/container (B5). Test
  env defines `BASEPATH=''`.
- **Dual `Model` lineage:** `Silver\Database\Model` (static active-
  record, extends `QueryObject`) vs `Silver\Core\Model` (instance,
  `protected string $primaryKey`). Reconcile/clarify in B4.
- `ServiceProvider` interface (`before(mixed $kernel)`/`register(mixed
  $app)`/`after()`) does not match how `Kernel` invokes providers
  (`before($req,$res)`/`after($req,$res)`, `register()` never called).
  Fix in B5.
- Dead `Kernel::call()` static (duplicates `DI`, no callers) — remove
  in B5.
- `Facade` keeps its own `static $objects` cache outside `Instances` —
  same class can exist twice. Unify in B5.
- Cosmetic: `Query\Delete` compiles `DELETE  FROM` (double space) —
  valid SQL, left verbatim (behaviour-preserving); tidy opportunistically.

> Phase C (runtime performance) still remains — its own pass when picked up.
