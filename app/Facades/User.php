<?php

declare(strict_types=1);

/**
 * SilverEngine  - PHP MVC framework
 *
 * @package   SilverEngine
 * @author    SilverEngine Team
 * @copyright 2015-2017
 * @license   MIT
 * @link      https://github.com/SilverEngine/Framework
 */
namespace App\Facades;

use Silver\Support\Facade;


/**
 * response event provider
 */
class User extends Facade
{

    protected static function getClass(): string
    {
        return \App\Helpers\User::class;
    }

}
