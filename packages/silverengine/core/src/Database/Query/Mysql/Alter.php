<?php
declare(strict_types=1);

namespace Silver\Database\Query\Mysql;

use Silver\Database\Query\Alter as P;

class Alter extends P
{
    protected static function compile($q) 
    {
        if (self::getTxCounter()) {
            throw new \Exception("DDL statements are not allowed during the transaction.");
        }
        return parent::compile($q);
    }
}