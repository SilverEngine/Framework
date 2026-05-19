<?php

/**
 * SilverEngine  - PHP MVC framework
 *
 * @package   SilverEngine
 * @author    SilverEngine Team
 * @copyright 2015-2017
 * @license   MIT
 * @link      https://github.com/SilverEngine/Framework
 */


/**
 * require initialisation file
 */
use Silver\ErrorHandler\Reporter;
use Silver\Core\Kernel;

require_once '../System/Core/init.php';

if (!is_dir('../vendor')) {
    exit('Vendor folder missing. Run: composer install');
}

/**
 * psr-4 autoloading
 */
require_once '../vendor/autoload.php';


/**
 * switch to root directory
 */
chdir(ROOT);

if (class_exists(Reporter::class)) {
    $errorHandler = new Reporter();
    $errorHandler->on();
}
// new ssd;
// exit();

/**
 * Load kernel
 */

$kernel = new Kernel();


/**
 * Load database config
 */

// FIXME: on ORM somewhere;
$database = \Silver\Core\Env::get('databases');


if ($database->on == true) {

    //    \Silver\Database\Query::connect('sqlite', 'sqlite:/Database/db.sqlite');

    \Silver\Database\Query::connect($database->local->driver, 'mysql:host=' . $database->local->hostname . ';dbname=' . $database->local->basename . ';charset=utf8', $database->local->username, $database->local->password);
    \Silver\Database\Query::setConnection($database->local->driver);
}

$kernel->loadRoutes();
$kernel->loadMiddlewares();
/**
 * - Load middlewares
 * - Load service run inside the run
 * -
 */
$kernel->run();
