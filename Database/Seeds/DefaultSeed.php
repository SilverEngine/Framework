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

namespace Database\Seeds;

use Silver\Database\Query as Seed;

class DefaultSeed
{

    private static $table;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public static function run($model, $table = false)
    {
        if ($model) {
            if ($table)
                self::$table = $table;
            else
                self::$table = $model;

            self::{$model}();
        } else
            return false;
    }

    /**
     * Run the database Users seed.
     *
     * @return void
     */
    protected static function users()
    {
        Seed::insert(static::$table, [
            'username' => 'admin',
            'password' => md5('admin'),
            'salt'     => 'ht4h4',
            'email'    => 'admin@badget.com',
            'active'   => 1,
        ])->execute();

    }

}
