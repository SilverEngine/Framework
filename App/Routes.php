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

Route::get('/', 'Welcome@welcome', 'home', 'public');
Route::get('/demo', 'Welcome@demo', 'home', 'public');

// Route for Test1 controller.
Route::get('/test1', 'Test1@get', 'test1', 'public');
