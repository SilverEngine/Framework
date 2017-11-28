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

namespace App;

use Silver\Core\Route;
use Silver\Core\Env;


if (Env::name() == 'local') {

    Route::get('/terminal', 'Terminal@index', 'terminal');
    Route::get('/terminal/manifest', 'Terminal@manifest', 'terminal.manifest');
    Route::get('/terminal/resource', 'Terminal@resource', 'terminal.resource');
    Route::post('/terminal/execute/{program}/{command}', 'Terminal@execute', 'terminal.execute');
    Route::post('/terminal/service/{program}/{service}', 'Terminal@service', 'terminal.service');
    Route::post('/terminal/logout', 'Terminal@logout', 'terminal.logout');
}