<?php

namespace Tests\Unit\Framework\Http;

use \PHPUnit\Framework\TestCase;
use Silver\Http\Curl;

class CurlTest extends TestCase
{
    protected $url = 'https://jsonplaceholder.typicode.com/todos/1';

    public function testGetDataFromGetMethod()
    {
        $get = Curl::get($this->url);
        var_dump($get);

        $this->assertEquals($get, false);
    }
}
