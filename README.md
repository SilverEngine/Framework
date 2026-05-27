# SilverEngine Framework

A lightweight PHP MVC framework — modernized for **PHP 8.5** with **zero external runtime dependencies**.

![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4.svg?logo=php&logoColor=white)
![PHP 8.1+](https://img.shields.io/badge/requires-PHP%208.1%2B-8892BF.svg)
![License MIT](https://img.shields.io/badge/license-MIT-green.svg)
![Runtime deps 0](https://img.shields.io/badge/runtime%20deps-0-success.svg)
![Status](https://img.shields.io/badge/status-modernized-blue.svg)

SilverEngine gives you routing, an MVC layer, a query builder, the Ghost
template engine, middleware, and a code-generation CLI — without pulling a
single package from Packagist. Composer is used **only** as the autoloader;
all first-party code ships in the repo as local path packages under
`Packages/`.

---

## Requirements

- PHP **8.1+** (developed and tested on 8.5)
- Composer 2.x (autoloading only — no network access required)
- PHP extensions: `pdo_sqlite` (bundled with PHP) for the default database

## Quick start

```bash
git clone git@github.com:SilverEngine/Framework.git
cd Framework

# Composer is used purely as the autoloader. No packages are downloaded;
# the only requirement (silverengine/error-handler) is a local path repo.
composer install

# Start the built-in dev server on the public/ docroot
php silver serve
```

Open <http://127.0.0.1:8000> — you should see the welcome page.
Stop the server with `Ctrl+C`.

For production, point your Apache/nginx docroot at `public/`. The shipped
`.htaccess` front-controller works out of the box; the router also resolves
correctly under nginx `try_files` and the PHP built-in server.

## The `silver` CLI

```bash
php silver help                       # full command reference
php silver serve [host:port]          # dev server (default 127.0.0.1:8000)
php silver migrate                    # run Database/Migrations

php silver g resource   <name>        # controller + model + view
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

Routes live in `App/Routes/`:

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

## Database

The default connection is **SQLite**, configured in `local.env.php`:

```php
'local' => [
    'driver'   => 'sqlite',
    'database' => 'Database/db.sqlite',  // auto-created on first connect
],
```

The file is created automatically and is git-ignored. Switch `driver` to
`mysql` (and fill in `hostname` / `basename` / `username` / `password`) to
use MySQL — the DSN is built per-driver.

## Project structure

```
App/            Your application
  Controllers/  Models/  Views/  Routes/  Middlewares/  Helpers/  Facades/
System/         Framework core (namespace Silver\)
  Core/  Http/  Database/  Engine/  Exception/  Helpers/  Support/
Packages/       First-party local Composer path packages
  silverengine/error-handler   Native error reporter (replaces Ouch)
Config/         App, routes, middleware, services, database config
Database/       Migrations/  Seeds/  (db.sqlite — git-ignored)
Storage/        Logs and runtime storage
public/         Web docroot — index.php front controller
Tests/          Test suite
silver          CLI entry point
local.env.php   Environment configuration
```

## Zero-dependency architecture

- `composer.json` declares **no external requires**. The single requirement,
  `silverengine/error-handler`, is resolved from a local `path` repository in
  `Packages/`, so `composer install` downloads nothing.
- Error reporting is handled by the first-party
  `Silver\ErrorHandler\Reporter` (no third-party error library). Fatal-class
  errors halt and render a debug page; warnings/notices/deprecations are
  logged but do not break the request.

## Contributing

Contributions are welcome. Please keep to:

1. The existing directory structure
2. PSR-4 autoloading, PSR-12 style
3. Framework classes under the `Silver\` namespace; app code under `App\`
4. PHP 8.1+ compatible code (no implicit nullable params, no removed APIs)

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
