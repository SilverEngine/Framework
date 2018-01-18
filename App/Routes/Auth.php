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

Route::get('/login', 'Auth/Login@get', 'auth_login');
Route::post('/login', 'Auth/Login@post', 'auth_try_login');
Route::get('/register', 'Auth/Register@get', 'auth_register');
Route::post('/register', 'Auth/Register@post', 'auth_try_register');
Route::get('/logout', 'Auth/Profile@logout', 'auth_logout');

Route::get('/profile', 'Auth/Profile@get', 'profile', 'user');
