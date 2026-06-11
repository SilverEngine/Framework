<?php
declare(strict_types=1);

define('APP_START', hrtime(true));

use Silver\ErrorHandler\Reporter;
use Silver\Core\Kernel;
use Silver\Core\Env;

/*
|--------------------------------------------------------------------------
| Bootstrap constants
|--------------------------------------------------------------------------
*/
error_reporting(E_ALL);
ini_set('display_errors', 'On');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
*/
if (!is_dir($root . 'vendor')) {
    exit('Vendor folder missing. Run: composer install');
}

require_once $root . 'vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Runtime context
|--------------------------------------------------------------------------
| Source of truth for paths + URL shape, registered in the container.
| The constants below are BC shims derived from it — new code should
| read RuntimeContext::current() instead.
*/
$context = Silver\Core\RuntimeContext::forWeb($root, $_SERVER);
Silver\Core\App::instance()->instances()
    ->instance(Silver\Core\RuntimeContext::class, $context);

define('DS', DIRECTORY_SEPARATOR);
define('BASEPATH', $context->basePath);
define('URL', $context->url);
define('CURRENT_URL', $context->currentUrl);
define('ROOT', $context->root);
define('EXT', '.php');

chdir(ROOT);

/*
|--------------------------------------------------------------------------
| Debug timer
|--------------------------------------------------------------------------
| Started here (right after autoload) so the full lifecycle is captured
| from the very first boot phase. Collection is a handful of hrtime()
| calls; the timeline is only ever rendered by the dev /debug page.
*/
dt()->start();
// Backfill the autoload + bootstrap window so heartbeat / debug see the
// full picture — APP_START → here was invisible because dt() itself can't
// observe its own pre-boot.
dt()->record('autoload + bootstrap', 'boot', APP_START, hrtime(true));
dt()->mark('autoload', 'boot');

/*
|--------------------------------------------------------------------------
| Environment (.env)
|--------------------------------------------------------------------------
*/
dt()->begin('Env::construct', 'boot');
Env::construct(ROOT);
dt()->end('Env::construct', 'boot');
dt()->mark('env loaded', 'boot');

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/
dt()->begin('error handlers', 'boot');
if (class_exists(Reporter::class)) {
    (new Reporter())->on();
}

$errorHandler = Silver\Core\App::instance()->instances()->make(Silver\Core\ErrorHandler::class);
$errorHandler->setFilter(E_ALL);
set_error_handler($errorHandler->handle_error(...), E_ALL);
set_exception_handler($errorHandler->handle_ex(...));
register_shutdown_function($errorHandler->handle_fatal(...));
dt()->end('error handlers', 'boot');

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
dt()->begin('database connect', 'boot');
// Populate the container-singleton ConnectionManager from config and bind
// the ORM (Model + Schema) facades. Honours the `databases.on` toggle —
// when DB is off this is a no-op and the ORM stays unbound.
$connections = Silver\Core\App::instance()->instances()
    ->make(\Silver\Orm\Connection\ConnectionManager::class);
\Silver\Orm\Schema\DatabaseBootstrap::boot($connections, ROOT);
dt()->end('database connect', 'boot');

// Promote last request's flash data (validation errors, old input,
// intended URL) to readable old-flash and clear the new-flash bucket —
// flashed values live for exactly one following request.
Silver\Http\Session::construct();

/*
|--------------------------------------------------------------------------
| Run
|--------------------------------------------------------------------------
*/
dt()->begin('kernel construct', 'kernel');
$kernel = new Kernel();
dt()->end('kernel construct', 'kernel');

dt()->begin('load routes', 'kernel');
$kernel->loadRoutes();
dt()->end('load routes', 'kernel');

dt()->begin('load middlewares', 'kernel');
$kernel->loadMiddlewares();
dt()->end('load middlewares', 'kernel');

define('APP_BOOT_MS', (hrtime(true) - APP_START) / 1e6);
header('X-Boot-Time: ' . number_format(APP_BOOT_MS, 2) . 'ms');

dt()->mark('kernel.run', 'kernel');
$kernel->run();
