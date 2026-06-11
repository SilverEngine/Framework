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

## In flight (uncommitted working tree) — finish first

- [ ] **DatabaseBootstrap unification.** `Orm/Schema/DatabaseBootstrap.php`
      (new) replaces `CliBootstrap` with two entry points: `build()` (CLI)
      and `boot()` (web). `public/index.php` previously still called the
      **deleted** `Silver\Database\DbDriver`/`Query::connect` — this diff
      removes the last legacy remnant and fixes the cold-CLI
      *"Not default connection found"* limitation.
      - [ ] add tests (legacy + new config shapes, `databases.on` off,
            missing-default fallback)
      - [ ] commit; update CLAUDE.md (gotcha about cold-CLI migrate is now
            stale, boot sequence step 6 changed)

---

## Phase D — Core rebuild: decouple, delete, unify

Goal: every core service instance-based + container-resolved; statics remain
only as thin facades. Characterization tests pinned before each change.

### D1 — Dead code sweep (cheap, do first)
- [ ] Delete `Core/Model.php` (legacy PDO model, **zero references** — verified)
- [ ] Delete `Support/Fake.php` / re-evaluate `FakeFactory` (no references found)
- [ ] Remove deprecated `Route` jails remnants, `Compiler::isFresh()`,
      `Config/Alias.php` (marked deprecated)
- [ ] Tidy `Query\Delete` double-space `DELETE  FROM` (cosmetic, pinned test)

### D2 — Session & Cookie as services
- [ ] `SessionStore` interface + native driver + in-memory array driver
      (tests); register in container; keep `Session::` static API as facade
- [ ] Same for `Cookie` (service owns encrypt/decrypt via `Crypter`,
      static API delegates)
- [ ] `FormRequest::merge()` must stop mutating `$_REQUEST`/`$_POST` —
      merge into the Request instance's own bag

### D3 — Kill bootstrap globals
- [ ] `BASEPATH`/`URL`/`ROOT`/`EXT`/`DS` → a `RuntimePaths`/`AppContext`
      value object built in `index.php`/`silver`, registered in the
      container; constants stay as BC shims that read from it
- [ ] `Route::url()` reads context, not the constant (removes the
      `phpunit.xml` `BASEPATH=""` hack)

### D4 — Provider & events unification
- [ ] `ServiceProvider`: add `register()` (container bindings, called for
      all providers before any `before()`); document lifecycle
- [ ] One event story: merge `Engine\Events\EventManager` into / on top of
      `Core\Hook` (or a small typed `EventDispatcher`); ORM observer
      dispatch plugs into the same thing; deprecate the loser

### D5 — Console kernel
- [ ] App-defined commands: `Silver\Console\Command` contract +
      registration via `config/commands.php`; `Command` enum keeps builtin
      dispatch, unknown tokens fall through to the registry
- [ ] Minimal `Input`/`Output` objects (args/flags parsing already exists
      in CLI — extract it); exit codes
- [ ] `php silver route:list`, `db:show`, `db:table <name>`

---

## Phase E — ORM completion (highest feature ROI, infra mostly exists)

- [ ] **Soft-delete scopes**: `withTrashed()` / `onlyTrashed()` /
      `restore()` (concern exists, scopes missing)
- [ ] **`paginate(int $perPage)`** → `Paginator` value object (data, total,
      page, lastPage, links) — JSON- and Wisp-friendly out of the box
- [ ] **Locking**: `forUpdate()` / `sharedLock()` (`QueryState::$lock`
      already defined — expose + compile per grammar)
- [ ] **Unions**: `union()` / `unionAll()` (`QueryState::$unions` already
      defined — same)
- [ ] **Upsert**: `upsert()` / `insertOrIgnore()` per driver
      (ON CONFLICT / ON DUPLICATE KEY)
- [ ] **Relation queries**: `whereHas()` / `whereDoesntHave()` /
      `whereExists()`, `withCount()`
- [ ] **Mutators + change tracking**: `setFooAttribute()` symmetry with
      accessors; `getOriginal()` / `getChanges()`
- [ ] **Relations**: `MorphToMany`, `associate()`/`dissociate()` on
      BelongsTo, `touch()` cascade
- [ ] **Schema**: `change()`/`modifyColumn()` (sqlite = table-rebuild path)
- [ ] **Seeders**: `Seeder` base + runner wired to existing
      `database/Seeds/`; `php silver db:seed`, `migrate:fresh --seed`
- [ ] **Factories**: minimal model factory (`definition()` + `count()` +
      `create()/make()`) — unblocks testing kit (G2)
- [ ] Nice-to-have: JSON where clauses (`whereJsonContains`), Castable
      interface for domain objects, AsCollection cast

---

## Phase F — HTTP & routing features

- [ ] **Route param injection + model binding**: typed controller params
      resolved from route vars (`DI::call` extension); `User $user` →
      `User::find($id)` → 404 on miss
- [ ] **Middleware groups + per-route middleware with params**
      (`'throttle:60,1'`, `'auth'`) — route-level, not only the global stack
- [ ] **General rate limiter**: store-backed (Apcu/array), sliding window;
      rebuild `Auth\Middleware\Throttle` on top (current one is in-memory
      per-process — useless behind php-fpm)
- [ ] **File uploads**: `UploadedFile` object on Request, validation rules
      (`file`, `image`, `mimes`, `max`), store via `FileSystem`
- [ ] **CORS middleware** (config-driven origins/methods/headers, preflight)
- [ ] **SecurityHeaders middleware** (CSP, HSTS, X-Frame-Options,
      X-Content-Type-Options, Referrer-Policy — sane defaults)
- [ ] **Signed URLs**: `URL::signed()` / `hasValidSignature()` —
      `Crypter::sign/verify` already does the work
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
