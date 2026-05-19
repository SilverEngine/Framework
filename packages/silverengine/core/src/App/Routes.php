<?php
declare(strict_types=1);

namespace App;

use Silver\Core\Route;
use Silver\Core\Env;

if (Env::get('debug')) {
    Route::get('/debug', 'Debug@index', 'debug');
}

if (Env::name() === 'local') {
    Route::get('/migrate/{modelName?}', 'Migrations@up', 'migrate');
    Route::get('/migrate-down/{modelName?}', 'Migrations@down', 'migrate-down');
    Route::get('/migrate-seed', 'Migrations@all', 'migrate-seed');
}
