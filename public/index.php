<?php
declare(strict_types=1);

define('APP_START', hrtime(true));

use Silver\ErrorHandler\Reporter;
use Silver\Core\Kernel;
use Silver\Core\Env;
use Silver\Support\DebugTimer;

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
define('SYS', ROOT . 'System' . DS);
define('CORE', SYS . 'Core' . DS);
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
| Debug timer (only when APP_DEBUG)
|--------------------------------------------------------------------------
*/
DebugTimer::mark('autoload', 'boot');

/*
|--------------------------------------------------------------------------
| Environment (.env)
|--------------------------------------------------------------------------
*/
DebugTimer::begin('Env::construct', 'boot');
Env::construct(ROOT);
DebugTimer::end('Env::construct', 'boot');

// Start profiler if debug mode
if (Env::get('debug')) {
    DebugTimer::start();
    DebugTimer::mark('autoload', 'boot');
    DebugTimer::mark('env loaded', 'boot');
}

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/
DebugTimer::begin('error handlers', 'boot');
if (class_exists(Reporter::class)) {
    (new Reporter())->on();
}

Silver\Core\ErrorHandler::setFilter(E_ALL);
set_error_handler(Silver\Core\ErrorHandler::handle_error(...), E_ALL);
set_exception_handler(Silver\Core\ErrorHandler::handle_ex(...));
register_shutdown_function(Silver\Core\ErrorHandler::handle_fatal(...));
DebugTimer::end('error handlers', 'boot');

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
DebugTimer::begin('database connect', 'boot');
$database = Env::get('databases');

if ($database && $database->on) {
    $local = $database->local;

    $dsn = match ($local->driver) {
        'sqlite' => 'sqlite:' . ROOT . $local->database,
        default  => $local->driver . ':host=' . $local->hostname . ';dbname=' . $local->basename . ';charset=utf8',
    };

    \Silver\Database\Query::connect($local->driver, $dsn, $local->username, $local->password);
    \Silver\Database\Query::setConnection($local->driver);
}
DebugTimer::end('database connect', 'boot');

/*
|--------------------------------------------------------------------------
| Run
|--------------------------------------------------------------------------
*/
$kernel = new Kernel();

DebugTimer::begin('load routes', 'kernel');
$kernel->loadRoutes();
DebugTimer::end('load routes', 'kernel');

DebugTimer::begin('load middlewares', 'kernel');
$kernel->loadMiddlewares();
DebugTimer::end('load middlewares', 'kernel');

define('APP_BOOT_MS', (hrtime(true) - APP_START) / 1e6);
header('X-Boot-Time: ' . number_format(APP_BOOT_MS, 2) . 'ms');

DebugTimer::mark('kernel.run', 'kernel');
$kernel->run();
