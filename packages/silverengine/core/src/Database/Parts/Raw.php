<?php
declare(strict_types=1);

namespace Silver\Database\Parts;

class Raw extends Part
{

    private $value;

    public function __construct($value) 
    {
        $this->value = $value;
    }

    protected static function compile($q) 
    {
        return $q->value;
    }
}