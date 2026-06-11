# SilverEngine — Roadmap

> Rewritten 2026-06-11. The old TODO (System→package migration, phases 0–10,
> A/B/C) is fully complete and lives in git history. This file is the
> forward roadmap: finish the core rebuild, then ship features.

---

## Where we are (verified against code, not docs)

**Done and green** — 293 tests, 602 assertions, 1 skipped; `composer check`
(PHPUnit + PHPStan lvl 5 over `Silver\Orm\`) passes.

- Package migration + PHP 8.4 modernization (phases 0–10, A) — complete
- Design-pattern audit (B1–B6) — complete, **including B5**: `Core\Container`
  has `bind()/singleton()/instance()/make()` with recursive autowiring;
  Kernel constructor-injects controllers + middleware; `Facade` routes
  through the container (no second cache)
- Perf phase C — measured; opcache is the lever, `optimize-autoloader` on
- `Silver\Orm\` — full typed ORM (connection/query/schema/model/relations/
  casts/cache), replaced `Silver\Database\` entirely
- Wisp (Inertia-protocol Vue 3 stack) + Ghost templating + shared data/composers
- Error pages (self-contained HTML + JSON envelope) + debug timeline/recorder
- `Silver\Crypto` — AES-256-GCM Crypter, HMAC sign/verify, Random tokens
- CSRF — TokenStore + VerifyCsrfToken + Ghost/Wisp wiring
- Auth — SessionGuard, Argon2id `Hash`, OrmUserProvider, Authenticate/Guest/
  Throttle middleware, `Auth` facade
- Validation — Validator (23+ rules), `ValidationResult`, `FormRequest` with
  content-negotiated failure
- `FileSystem` contract + `LocalFileSystem` (hookable), `Hook` AOP system

---

## In flight — DONE (committed)

- [x] **DatabaseBootstrap unification.** `build()` (CLI) + `boot()` (web)
      share one normalisation core; removed the last `Silver\Database\`
      remnant from `public/index.php`; fixed the cold-CLI *"Not default
      connection found"* limitation. 8 tests; CLAUDE.md updated.

---

## Phase D — Core rebuild: decouple, delete, unify

Goal: every core service instance-based + container-resolved; statics remain
only as thin facades. Characterization tests pinned before each change.

### D1 — Dead code sweep — DONE
- [x] Deleted `Core/Model.php` (legacy PDO model, zero references)
- [x] Deleted `Support/Fake.php` (unreferenced; `FakeFactory` was already gone)
- [x] Removed deprecated `Route` jails, `Compiler::isFresh()` +
      deprecated `depsFrom()`, `Config/Alias.php`
- [x] ~~`Query\Delete` double-space~~ — already gone with the legacy tree

### D2 — Session & Cookie as services — DONE
- [x] `SessionStore` contract + `NativeSessionStore` + `ArraySessionStore`;
      container singletons; `Session::` static API kept as facade
- [x] `CookieJar` instance service; `Cookie::` facade delegates,
      `set()->encrypt()` chain preserved
- [x] `FormRequest::merge()` now writes a per-instance overlay on Request
      (`mergeInput()`); superglobals untouched
- [x] **Bonus fix:** flash rotation (`Session::construct()`) had zero
      callers — flashed values never expired. Now wired at request boot;
      flash lives exactly one following request.

### D3 — Kill bootstrap globals — DONE
- [x] `Silver\Core\RuntimeContext` readonly value object (root, basePath,
      url, currentUrl); `forWeb()` in index.php, `forCli()` in silver,
      `detect()` container fallback for tests; constants remain as BC
      shims derived from it — no framework code reads them anymore
- [x] `Route::url()` + Request/WispResponse/Template/Vite/Compiler/
      Library/Log/AccessLog/Heartbeat/DebugController converted;
      `phpunit.xml` `BASEPATH=""` hack removed

### D4 — Provider & events unification — DONE
- [x] `ServiceProvider::register($container)` phase — runs for ALL
      providers before any `before()`; `AbstractProvider` no-op base
- [x] One event story: deleted legacy `Engine\Events\EventManager`
      (hardcoded class lookup, no real callers); new
      `Silver\Events\Dispatcher` typed pub-sub container singleton.
      `Hook` stays the method-interception primitive; ORM observer
      `EventDispatcher` stays specialised (revisit only if someone
      needs global model events).

### D5 — Console kernel — DONE
- [x] `Silver\Console\Contracts\Command` + `Registry`; app commands via
      `config/commands.php` (token => class, overrides builtins);
      unknown enum tokens fall through to the registry, exit 1 on miss
- [x] `Input` (args/flags) + `Output` (stdout/stderr streams, table
      renderer); exit codes from `handle()`
- [x] `php silver route:list`, `db:show`, `db:table <name>
      [--connection=]` — all three verified live

> **Phase D complete.** Core is decoupled: services are container-
> resolved, statics are facades, globals are shims, providers have a
> bindings phase, the CLI is extensible.

---

## Phase E — ORM completion (highest feature ROI, infra mostly exists)

- [x] **Soft-delete scopes**: `withTrashed()` / `onlyTrashed()` /
      `restore()` — DONE (E2)
- [x] **`paginate(int $perPage)`** → `Paginator` value object (data, total,
      page, lastPage, links) — DONE (E1)
- [x] **Locking**: `forUpdate()` / `sharedLock()` — DONE (E1)
- [x] **Unions**: `union()` / `unionAll()` — DONE (E1)
- [x] **Upsert**: `upsert()` / `insertOrIgnore()` per driver — DONE (E1)
- [x] **Relation queries**: `whereHas()` / `whereDoesntHave()` /
      `whereExists()`, `withCount()` — DONE (E3)
- [x] **Mutators + change tracking**: `setFooAttribute()` symmetry with
      accessors; `getOriginal()` / `getChanges()` — DONE (E4)
- [x] **Relations**: `MorphToMany`/`morphedByMany` (E7),
      `associate()`/`dissociate()` on BelongsTo, `touch()` cascade (E6)
- [x] **Schema**: `change()` — native MODIFY/ALTER COLUMN on mysql/pgsql,
      table-rebuild path on sqlite (`SqliteTableRebuilder`) — DONE (E8)
- [x] **Seeders**: `Seeder` base + runner wired to existing
      `database/Seeds/`; `php silver db:seed`, `migrate:fresh --seed` — DONE (E5)
- [x] **Factories**: minimal model factory (`definition()` + `count()` +
      `create()/make()`) — DONE (E5)
- [x] Nice-to-have: `whereJsonContains` (per-driver JSON_CONTAINS /
      jsonb @> / json_each EXISTS), `Castable` interface for domain
      objects, `AsCollection` cast — DONE (E9)

**Phase E complete** — E1 (upsert/unions/locks/paginate), E2 (soft-delete
scopes), E3 (whereHas/withCount), E4 (mutators/changes), E5
(seeders/factories), E6 (associate/touch), E7 (MorphToMany), E8
(Schema change()), E9 (JSON wheres + casts).

---

## Phase F — HTTP & routing features

- [x] **Route param injection + model binding**: typed controller params
      coerced from route vars (int/float/bool under strict_types);
      `User $user` → `User::find($id)` → 404 on miss, nullable param →
      null on miss; FQN-keyed container instances still win — DONE (F1)
- [x] **Middleware groups + per-route middleware with params**:
      routes carry a middleware list (legacy 'public' tag dropped),
      `group(['middleware' => ...])` inherits/stacks, config
      `Middleware.php` aliases+groups, `MiddlewareResolver` parses
      `'throttle:60,1'` (positional params cast onto scalar ctor params,
      service deps autowired), Kernel runs the chain around the action
      — DONE (F2)
- [x] **General rate limiter**: `Silver\Support\RateLimiter` — sliding
      window over any `CacheStore` (APCu default, process-shared array
      fallback), injectable clock; `Throttle` rebuilt on top with
      Retry-After + X-RateLimit-* headers — DONE (F3)
- [x] **File uploads**: `UploadedFile` value object (real mime via
      finfo, test mode for fakes), `Request::file()/hasFile()/
      mergeFiles()`, validation rules `file`/`image`/`mimes:` +
      file-aware `min:`/`max:` (KB), `store()` via `FileSystem` with
      random-name default — DONE (F4)
- [x] **CORS middleware**: `Silver\Http\Middleware\Cors` + core
      `config/Cors.php` (paths/origins incl. fnmatch wildcards/methods/
      headers/expose/max_age/credentials); preflight short-circuits 204;
      credentials forces origin echo over '*' — DONE (F5)
- [x] **SecurityHeaders middleware**: sane defaults (SAMEORIGIN/
      nosniff/strict-origin-when-cross-origin), HSTS on HTTPS only
      (`Request::isSecure()` added, proxy-aware), CSP/Permissions-Policy
      opt-in via `config/Security.php`, never clobbers per-route
      headers — DONE (F6)
- [x] **Signed URLs**: `URL::signed()` / `temporarySigned()` /
      `hasValidSignature(Request|string)` — HMAC over path+query incl.
      'expires', URL-safe signature param — DONE (F7)
- [ ] **Validation v2**: conditional rules (`required_if`/`required_unless`),
      closure rules, file/image rules (ties into uploads)

---

## Phase G — Auth completion & DX

### G1 — Auth features
- [ ] Remember-me (hashed token table + cookie, rotate on use)
- [ ] Password reset flow (token via `Random`, mail, expiry) — needs G3 mail
- [ ] Email verification (signed-URL based — reuses Phase F)
- [ ] **Authorization**: `Gate::define()` + policy classes +
      `$user->can()` + middleware + Ghost `#can` directive
- [ ] API token guard (bearer; hashed tokens, abilities) — the `AuthManager`
      v2 comment

### G2 — Testing kit
- [ ] `Silver\Testing\TestCase`: boot app for tests, `get()/post()/json()`
      returning a `TestResponse` with `assertStatus/assertSee/assertJson/
      assertRedirect`
- [ ] In-memory sqlite + migrate-fresh-per-class helpers; model factories (E)
- [ ] Fakes: `Log`, `Mail` (assert-sent)
- [ ] Backfill zero-coverage subsystems: FileSystem, Helpers, ErrorHandler,
      Hookable

### G3 — Supporting services
- [ ] Mail v2: attachments, Ghost-templated bodies, driver contract
      (sendmail/SMTP/log) — prerequisite for password reset
- [ ] Cache facade: one `CacheStore`-backed `Cache::get/put/remember` with
      TTL + tags (Orm stores already exist — generalize, don't duplicate)
- [ ] Promote `Orm\Collection` to `Silver\Support\Collection` (general use)
- [ ] Localization: parameter interpolation + pluralization in `trans()`,
      locale-switching middleware

---

## Explicit non-goals (decided, revisit only with a reason)

- **PSR-7/15/11 compliance** — first-party contracts are the design;
  dependency-light beats interop here. If interop is ever needed, write
  adapters, don't rewrite Request/Response.
- **Queue + scheduler** — out of scope until a real app needs them; design
  the console kernel (D5) so a worker command is possible later.
- **classmap-authoritative** — still forbidden (dynamic `class_exists`
  resolution in Kernel/Dialect/QueryType).

---

## Suggested order

1. Commit the in-flight DatabaseBootstrap work (tests + CLAUDE.md update)
2. **D1** dead-code sweep (one small PR)
3. **E** ORM completion — biggest user-visible win, infra half-built
4. **D2–D5** core decoupling, interleaved as focused PRs
5. **F** HTTP features (uploads/rate-limit/CORS/headers as separate PRs)
6. **G** auth completion + testing kit (G2 can start any time after E
   factories land)

## Quality gates (unchanged)

- Characterization tests pinned **before** structural refactors
- Per-PR: `composer check` green (PHPUnit + PHPStan); extend stan beyond
  `Silver\Orm\` as phases touch new trees (target: whole `src/` at lvl 5)
- Behaviour changes and refactors in separate commits (`fix(...)` vs
  `refactor(...)`)
