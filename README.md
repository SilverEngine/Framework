# SilverEngine Framework

A lightweight PHP **DMVC** framework — *Dynamical Model View Controller* —
modernized for **PHP 8.4+** and **dependency-free**.

![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg?logo=php&logoColor=white)
![DMVC](https://img.shields.io/badge/architecture-DMVC-blue.svg)
![License MIT](https://img.shields.io/badge/license-MIT-green.svg)
![Runtime deps 0](https://img.shields.io/badge/runtime%20deps-0-success.svg)
![Status](https://img.shields.io/badge/status-modernized-blue.svg)

SilverEngine is a **D**ynamical **M**odel **V**iew **C**ontroller framework:
routing, models with a fluent query builder, the Ghost template engine,
server-driven Vue (Wisp), middleware, and a code-generation CLI — without
pulling a single package from Packagist. Composer is used **only** as the
autoloader; all first-party code ships in the repo as a local Composer
**path** package under `Packages/`.

---

## Requirements

- PHP **8.4+**
- Composer 2.x (autoloading only — no network access required)
- PHP extensions: `pdo_sqlite` (bundled with PHP) for the default database
- Node 20+ / npm — **build-time only**, for the Wisp frontend toolchain

## Quick start

```bash
git clone git@github.com:SilverEngine/Framework.git
cd Framework

# Composer is used purely as the autoloader. Nothing is downloaded —
# silverengine/core is resolved from a local path repository.
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

For production, point your Apache/nginx docroot at `public/`. The shipped
`.htaccess` front-controller works out of the box; the router also resolves
correctly under nginx `try_files` and the PHP built-in server.

## The `silver` CLI

```bash
php silver help                       # full command reference
php silver serve [host:port]          # dev server (default 127.0.0.1:8000)
php silver migrate                    # run Database/Migrations

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

## Routing

`Config/Routes.php` is an ordered list of route files to include — the first
entry is the core system routes (inside the package); your routes follow:

```php
// App/Routes/Web.php
Route::get('/',     'Welcome@welcome', 'home');
Route::get('/demo', 'Welcome@demo',    'demo');

// App/Routes/Api.php
Route::group(['prefix' => 'api'], function () {
    Route::get('/', function () { /* ... */ });
});
```

Unknown routes return a proper **404** (rendered via the `ErrorHandler`
middleware → `App/Views/errors/404`).

## Frontend — Wisp (server-driven Vue)

An Inertia-style stack baked into Ghost: **Vite + Vue 3 + TypeScript +
Tailwind 4** with the official `@inertiajs/vue3` client. Controllers return
`wisp('Page', $props)` instead of a Ghost view; Ghost renders the app shell
on full loads and the page object as JSON on client navigations. No client
router, no separate API layer. Classic Ghost `.ghost.tpl` rendering still
works and coexists (welcome, errors).

```php
public function welcome(): mixed
{
    return wisp('Welcome', ['name' => 'World']);
}
```

## Database

The default connection is **SQLite**, configured via `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=Database/db.sqlite   # auto-created on first connect, git-ignored
```

Switch the driver to `mysql` / `pgsql` (and fill in host / database /
username / password) to use those instead — the DSN is built per-driver.

## Project structure

```
App/            Your application
  Controllers/  Models/  Views/  Routes/  Middlewares/  Resources/ (js, css)
Packages/       First-party local Composer path package
  silverengine/core   Framework core (namespace Silver\)
Config/         App, routes, middleware, services, database config
Database/       Migrations/  Seeds/  (db.sqlite — git-ignored)
Storage/        Logs and runtime storage
public/         Web docroot — index.php front controller, build/ assets
Tests/          Test suite
silver          CLI entry point
.env            Environment configuration
```

## Dependency-free architecture

- `composer.json` pulls **nothing from Packagist** for the framework itself.
  `silverengine/core` is resolved from a local `path` repository in
  `Packages/`, so `composer install` downloads no framework code.
- Error reporting is the first-party `Silver\ErrorHandler\Reporter`, now part
  of `silverengine/core` (no separate package, no third-party error library).
  Fatal-class errors halt and render a debug page; warnings, notices and
  deprecations are logged but do not break the request.
- Frontend dependencies (Vite/Vue/Tailwind) are **build-time only** — they
  never run on the server and are not required to serve the app.

## Contributing

Contributions are welcome. Please keep to:

1. The existing directory structure
2. PSR-4 autoloading, PSR-12 style
3. Framework classes under the `Silver\` namespace; app code under `App\`
4. PHP 8.4+ idioms — `declare(strict_types=1)`, typed/readonly properties,
   constructor promotion, `match`, `final` on leaf classes

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
