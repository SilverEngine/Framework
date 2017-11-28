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

namespace Tests\Unit\Http;

use \PHPUnit\Framework\TestCase;
use Silver\Http\Request;


class RequestTest extends TestCase
{

    private $request;
    private $requestWithUri;

    /**
     * init request
     */
    public function setUp()
    {
        $this->request = new Request();
        $this->requestWithUri = new Request("silver/users/1");
    }

    /**
     * confirm that there is a request uri
     */
    public function testUriIsString()
    {
        $this->assertInternalType('string', $this->requestWithUri->getUri());
    }

    /**
     * confirm that requested methods are available
     */
    public function testRequestMethodIsAvailable()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $requestMethod = strtoupper($this->request->method());
        $this->assertContains($requestMethod, $methods);
    }

    /**
     * confirm that request header returns an array
     */
    public function testRequestHeaderReturnArray()
    {
        $this->assertInternalType("array", $this->request->header());
    }

    /**
     * confirm that request header returns a string
     */
    public function testRequestHeaderReturnString()
    {
        $this->assertInternalType("string", $this->request->header('HOST'));
    }

    /**
     * confirm that request all return an array of all requests
     */
    public function testRequestAll()
    {
        $this->assertInternalType('array', $this->request->all());
    }


    public function testSegmentReturnString()
    {
        $this->assertInternalType("string", $this->request->segment(0));
    }

    public function testSegmentIsNull()
    {
        $this->assertEquals($this->request->segment(''), NULL);
    }

}