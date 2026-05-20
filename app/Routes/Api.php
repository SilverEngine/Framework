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

/** @var \Silver\Core\Route $route */
$route->group(['prefix' => 'api'], function () use ($route) {
    $route->get('/', function () {
        return 'Welcome to the api';
    });
});
