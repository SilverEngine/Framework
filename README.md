# SilverEngine Framework

A lightweight PHP **DMVC** framework — *Dynamical Model View Controller* —
modernized for **PHP 8.4+** and dependency-light.

![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg?logo=php&logoColor=white)
![DMVC](https://img.shields.io/badge/architecture-DMVC-blue.svg)
![Runtime deps 2](https://img.shields.io/badge/runtime%20deps-2-success.svg)
![License MIT](https://img.shields.io/badge/license-MIT-green.svg)
![Status](https://img.shields.io/badge/status-modernized-blue.svg)

> **Two tiny runtime deps, build-time-only frontend, framework code in the
> repo.** Composer is used **only** as the autoloader — `silverengine/core`
> ships as a local Composer `path` package under `packages/`, never pulled
> from Packagist. The only Packagist runtime requires are
> `vlucas/phpdotenv` and `nejcc/php-datatypes`.

SilverEngine is a **D**ynamical **M**odel **V**iew **C**ontroller framework:
routing, a fluent query builder + Model, the Ghost template engine,
server-driven Vue (Wisp), middleware, an IoC container with autowiring,
a code-generation CLI, a built-in debug profiler with a request recorder,
and `optimize` / `optimize:clear` cache commands.

---

## Requirements

- PHP **8.4+**
- Composer 2.x (autoloading only — no network access required for framework code)
- PHP extensions: `pdo_sqlite` (bundled with PHP) for the default database
- Node 20+ / npm — **build-time only**, for the Wisp frontend toolchain

## Quick start

```bash
git clone git@github.com:SilverEngine/Framework.git
cd Framework

# Composer is used purely as the autoloader. silverengine/core is
# resolved from a local path repository; nothing framework-related is
# downloaded from Packagist.
composer install

# Start the built-in dev server on the public/ docroot
php silver serve
```

Open <http://127.0.0.1:8000> — you should see the welcome page.
Stop the server with `Ctrl+C`.

For frontend work (Wisp / Vue with HMR), run the full dev stack:

```bash
npm install
composer dev          # PHP server + Vite, concurrently
npm run build         # type-check + production assets → public/build/
```

For production, point your Apache / nginx docroot at `public/`. The shipped
`.htaccess` front-controller works out of the box; the router also resolves
correctly under nginx `try_files` and the PHP built-in server.

## The `silver` CLI

```bash
php silver help                       # full command reference
php silver serve [host:port]          # dev server (default 127.0.0.1:8000)
php silver migrate                    # run database/Migrations

php silver optimize                   # cache merged config + routes,
                                      #   run composer dump-autoload -o
php silver optimize:clear             # clear those caches

php silver g resource   <name>        # controller + model + view + routes
php silver g controller <name>
php silver g model      <name>
php silver g view       <name>
php silver g facade     <name>
php silver g helper     <name>
php silver d resource   <name>        # delete a CRUD resource
```

`serve` accepts a bare port (`php silver serve 8080`) or a full
`host:port` (`php silver serve 0.0.0.0:8080`).

After editing `.env`, a `config/` override, or a route file, run
`php silver optimize:clear` (or `optimize` to rebuild).

## Routing

`config/Routes.php` is an ordered list of route files to include — the first
entry is the core system routes (inside the package); your routes follow:

```php
// app/Routes/Web.php
Route::get('/',     'Welcome@welcome', 'home');
Route::get('/demo', 'Welcome@demo',    'demo');

// app/Routes/Api.php
Route::group(['prefix' => 'api'], function () {
    Route::get('/widgets', 'Widgets@list');
});
```

Unknown routes return a proper **404** (rendered by the `ErrorHandler`
middleware → `app/Views/errors/404`). Uncaught exceptions render a
**self-contained** 500 page (inline CSS — never breaks even if the asset
build is broken). API requests (`/api/…`) get a JSON error envelope:

```json
{ "error": { "status": 500, "message": "…",
             "exception": "App\\…", "file": "…", "line": 42, "trace": [ … ] } }
```

In production, only `status` + `message` are returned — class, file, line
and trace are debug-only.

## Frontend — Wisp (server-driven Vue)

An Inertia-style stack baked into Ghost: **Vite + Vue 3 + TypeScript +
Tailwind 4** with the official `@inertiajs/vue3` client. Controllers return
`wisp('Page', $props)` instead of a Ghost view; Ghost renders the app shell
on full loads and the page object as JSON on client navigations. No client
router, no separate API layer.

```php
public function welcome(): mixed
{
    return wisp('Welcome', ['name' => 'World']);
}
```

Every Wisp page is wrapped in a **persistent default layout**
(`app/Resources/js/Layouts/Layout.vue`) — opt out per page with
`defineOptions({ layout: null })` or supply your own. Classic Ghost
`.ghost.tpl` rendering still works and coexists (`/`, errors, `/demo`).

## Container & dependency injection

`Silver\Core\Container` is a real IoC container:

```php
$c = \Silver\Core\App::instance()->instances();

$c->bind(MailerInterface::class, SmtpMailer::class);
$c->singleton(Clock::class, fn () => new SystemClock());

// Controllers and middleware get constructor injection automatically:
class UserController
{
    public function __construct(private UserRepo $repo) {}
}
```

The legacy `Instances` registry surface (`register`/`registerNamed`/`get`/
`getAll`) is preserved — `Container` is a strict superset, so existing
calls keep working unchanged.

## Database

The default connection is **SQLite**, configured via `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/db.sqlite   # auto-created on first connect, git-ignored
```

Switch the driver to `mysql` / `pgsql` (and fill in host / database /
username / password) to use those — the DSN is built per-driver. The query
runtime is split into a small `ConnectionManager` (registry + lazy PDO),
a `TransactionManager` (nested `BEGIN/COMMIT/ROLLBACK` with savepoints),
and `Db` as a thin backward-compatible facade. Per-driver SQL variance is
resolved by a typed `Dialect` strategy + a `DbDriver` / `QueryType` enum.

## Config — defaults in core, overrides in your app

Framework config defaults live in `packages/silverengine/core/src/Config/`.
Your `config/` directory is an **overrides overlay** that **deep-merges**
over them:

```php
// packages/silverengine/core/src/Config/Recorder.php (default)
return ['enabled' => true, 'limit' => 50, 'ignore' => ['/debug', '…']];

// config/Recorder.php (your override)
return ['limit' => 200];

// effective: ['enabled' => true, 'limit' => 200, 'ignore' => ['/debug', '…']]
```

Associative arrays merge recursively (your keys win); a list or scalar
override replaces wholesale. See `config/README.md` for the full rules.

## Debug — timeline + request recorder

When `APP_DEBUG=true`, every request's full lifecycle is timed (autoload →
Env → DB connect → load routes/middlewares → request → per-middleware →
controller resolve / action → view render → response sent) and persisted
to `storage/debug/recordings/` (file-based, ring-buffered). Browse to
`/debug` to see:

- **Overview** — request, environment, DB, packages
- **Timeline** — colored waterfall of the current request
- **Recordings** — list of recent requests; click any to replay its
  *complete* lifecycle in the waterfall (incl. phases the live page
  can't show about itself)

`/debug`, `/build`, `/favicon`, `/robots` are excluded from recording by
default; override `recorder.ignore` in `config/Recorder.php`.

## Tests

PHPUnit 12 ships as `require-dev` (dev-only — runtime stays dependency-light):

```bash
composer test            # phpunit
vendor/bin/phpunit       # equivalent
```

The suite lives in `tests/Unit/**Test.php`.

## Project structure

```
app/            Your application
  Controllers/  Models/  Views/  Routes/  Middlewares/  Facades/
  Resources/    js (Layouts/, Pages/, app.ts), css (app.css), views (Wisp shell)
packages/       First-party local Composer path package
  silverengine/core   Framework core (namespace Silver\)
    src/Config/       Framework config DEFAULTS
config/         Your config OVERRIDES (deep-merged over the defaults)
database/       Migrations/  Seeds/  (db.sqlite — git-ignored)
storage/        Logs/  debug/recordings/  cache/  (all git-ignored)
public/         Web docroot — index.php + .htaccess; build/ assets
tests/          PHPUnit suite (Unit/)
silver          CLI entry point
.env            Environment configuration (APP_DEBUG, DB_*, …)
```

## Architecture notes

- **Composer pulls nothing from Packagist for the framework itself.**
  `silverengine/core` is resolved from a local `path` repository in
  `packages/`. Runtime depends only on `vlucas/phpdotenv` and
  `nejcc/php-datatypes`.
- **Error reporting** is the first-party `Silver\ErrorHandler\Reporter`,
  inlined in `silverengine/core`. Fatal-class errors halt and render a
  self-contained debug page; warnings, notices and deprecations are
  logged but do not break the request.
- **Error pages** (`500`, `404`) are fully self-contained (inline CSS) —
  they render even if the asset build, the database or the rest of the
  app is broken. In debug they show the real exception class, message,
  file/line, request context (method/URI/route/IP/query/input), source
  snippet and a normalized stack trace.
- **Frontend dependencies** (Vite/Vue/Tailwind) are **build-time only**.
- **Production tip:** run `php silver optimize` after deploys; ensure PHP
  has **opcache** enabled (the dominant runtime lever). Do **not** use
  Composer's `--classmap-authoritative` — the framework relies on
  dynamic class resolution that an authoritative classmap would break.

## Contributing

Contributions are welcome. Please keep to:

1. The existing directory structure
2. PSR-4 autoloading, PSR-12 style
3. Framework classes under the `Silver\` namespace; app code under `App\`
   (directories lowercased, namespaces PascalCase)
4. PHP 8.4+ idioms — `declare(strict_types=1)`, typed/readonly properties,
   constructor promotion, `match`, enums for finite sets, `final` on leaf
   classes
5. Tests for behavioural changes — `tests/Unit/Framework/...`

Special thanks to past contributors:
[lotfio-lakehal](https://github.com/lotfio),
[nmarulo](https://github.com/nmarulo),
[antigov](https://github.com/antigov),
[mawaishanif](https://github.com/mawaishanif).

## Security

If you discover a security vulnerability, please email
**support@silverengine.net** rather than opening a public issue.

## License

Open-source software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
