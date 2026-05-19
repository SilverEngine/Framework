<?php

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Curl;

class CurlTest extends TestCase
{
    protected string $url = 'https://jsonplaceholder.typicode.com/todos/1';

    public function testGetDataFromGetMethod(): void
    {
        $this->markTestSkipped('Network-dependent: hits a live external endpoint.');

        $get = Curl::get($this->url);

        $this->assertNotFalse($get);
    }
}
