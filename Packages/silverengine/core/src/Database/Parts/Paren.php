<?php
declare(strict_types=1);

namespace Silver\Database\Parts;

class Paren extends Part
{
    private $form;

    public function __construct($form) 
    {
        $this->form = $form;
    }

    protected static function compile($q) 
    {
        return "({$q->form})";
    }
}