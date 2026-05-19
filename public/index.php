<?php
declare(strict_types=1);

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
| Environment (.env)
|--------------------------------------------------------------------------
*/
Env::construct(ROOT);

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/
if (class_exists(Reporter::class)) {
    (new Reporter())->on();
}

Silver\Core\ErrorHandler::setFilter(E_ALL);
set_error_handler(Silver\Core\ErrorHandler::handle_error(...), E_ALL);
set_exception_handler(Silver\Core\ErrorHandler::handle_ex(...));
register_shutdown_function(Silver\Core\ErrorHandler::handle_fatal(...));

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Run
|--------------------------------------------------------------------------
*/
$kernel = new Kernel();
$kernel->loadRoutes();
$kernel->loadMiddlewares();
$kernel->run();
