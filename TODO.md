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

## Phase 4 — HTTP layer [NEXT]

- [ ] `Http/Request.php` — migrate, typed properties, union types
- [ ] `Http/Response.php` — migrate, fix `Silver\Core\Render` ref
- [ ] `Http/Session.php` — migrate
- [ ] `Http/Cookie.php` — migrate
- [ ] `Http/Curl.php` — migrate
- [ ] `Http/Redirect.php` — migrate
- [ ] `Http/Validator.php` — migrate
- [ ] `Http/View.php` — migrate
- [ ] **Verify:** full request lifecycle works

---

## Phase 5 — Database / Query Builder

- [ ] `Database/Query.php` — migrate (central class)
- [ ] `Database/Db.php`
- [ ] `Database/Model.php`
- [ ] `Database/Compiler.php`
- [ ] `Database/QueryObject.php`
- [ ] `Database/Relation.php`
- [ ] `Database/Source.php`
- [ ] `Database/DBCreator.php`
- [ ] `Database/Parts/*` — bulk migrate all parts + DB-specific subdirs
- [ ] `Database/Query/*` — Select, Insert, Update, Delete, Create, Drop, Alter + variants
- [ ] `Database/Traits/*` — QueryColumns, QueryFrom, QueryJoin, QueryWH, etc.
- [ ] `Database/Source/*` — Query, Table, Model sources
- [ ] **Verify:** CRUD operations, migrations controller, SQLite + MySQL paths

---

## Phase 6 — Support & Helpers

- [ ] `Support/Facade.php`
- [ ] `Support/Fake.php` + `FakeFactory.php`
- [ ] `Support/Log.php`
- [ ] `Support/Crypter.php`
- [ ] `Support/Git.php`
- [ ] `Support/SMail.php`
- [ ] `Helpers/String.php`
- [ ] `Helpers/Path.php`
- [ ] `Helpers/URL.php`
- [ ] `Helpers/HTMLElement.php`
- [ ] **Verify:** helpers accessible, logging works

---

## Phase 7 — Engines

- [ ] `Engine/CLI/index.php` — migrate + modernise CLI arg parsing
- [ ] `Engine/Events/EventManager.php`
- [ ] `Engine/Ghost/Template.php` — migrate template engine
- [ ] **Verify:** `php silver serve`, event dispatch, template rendering

---

## Phase 9 — System App (framework defaults)

- [ ] `App/Controllers/SystemController.php`
- [ ] `App/Controllers/MigrationsController.php`
- [ ] `App/Middlewares/*` (ErrorHandler, AccessLog, ApiTransform, Version)
- [ ] `App/Routes.php`
- [ ] `App/Views/*` — copy views into package
- [ ] **Verify:** system routes (/system, /migrations) respond

---

## Phase 10 — Cleanup & finalize

- [ ] Delete `System/` directory entirely
- [ ] Remove `"Silver\\": "System/"` from root `composer.json`
- [ ] Audit `Config/Providers.php` — remove stale namespace mappings
- [ ] Run full test suite (`Tests/`)
- [ ] Update `Packages/silverengine/error-handler` to `php >=8.4`
- [ ] Tag `silverengine/core` v0.1.0

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
- [ ] Enums for finite value sets (HTTP methods, DB drivers)
- [ ] `array_find`, `array_any`, `array_all` (PHP 8.4+)
- [ ] Property hooks where they simplify getters/setters
- [ ] Remove remaining legacy phpdoc that duplicates native types
