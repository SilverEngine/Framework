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

$PREFIX = preg_replace('{/index.php$}', '', str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));
$HOST = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '')
    . '://' . $_SERVER['SERVER_NAME']
    . ($_SERVER['SERVER_PORT'] != 80 ? ':' . $_SERVER['SERVER_PORT'] : '');

define('DS', DIRECTORY_SEPARATOR);
define('BASEPATH', $PREFIX);
define('URL', $HOST . $PREFIX);
define('CURRENT_URL', $HOST . $_SERVER['REQUEST_URI']);
define('ROOT', dirname(__DIR__) . DS);
define('EXT', '.php');

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
*/
if (!is_dir(ROOT . 'vendor')) {
    exit('Vendor folder missing. Run: composer install');
}

require_once ROOT . 'vendor/autoload.php';

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
$database = Env::get('databases');

if ($database && $database->on) {
    $local = $database->local;

    $dsn = \Silver\Database\DbDriver::dsn($local->driver, ROOT, $local);

    \Silver\Database\Query::connect($local->driver, $dsn, $local->username, $local->password);
    \Silver\Database\Query::setConnection($local->driver);
}
dt()->end('database connect', 'boot');

/*
|--------------------------------------------------------------------------
| Run
|--------------------------------------------------------------------------
*/
$kernel = new Kernel();

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
