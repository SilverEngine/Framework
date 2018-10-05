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

namespace App\Routes;

use Silver\Core\Route;

Route::get('/', 'Welcome@welcome', 'home', 'public');
Route::get('/demo', 'Welcome@demo', 'home', 'public');

// Route for Nejc controller.
// Route::get('/nejc', 'Nejc@get', 'nejc', 'public');    -- removed by resource manager

// Route for Nejc controller.
// Route::get('/nejc', 'Nejc@get', 'nejc', 'public');    -- removed by resource manager

// Route for Nejc controller.
// Route::get('/nejc', 'Nejc@get', 'nejc', 'public');    -- removed by resource manager

// Route for Nejc controller.
// Route::get('/nejc', 'Nejc@get', 'nejc', 'public');    -- removed by resource manager

// Route for Nejc controller.
Route::get('/nejc', 'Nejc@get', 'nejc', 'public');
