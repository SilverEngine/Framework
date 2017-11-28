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
use Silver\Http\Response;


class ResponseTest extends TestCase
{
    private $response;

    public function setUp(){
        $this->response = new Response();
    }

    public function testSetHeader(){
        $this->assertInternalType('null', $this->response->setHeader('test_unit', 'header_in'));
    }

    public function testGetHeader(){
        $this->assertInternalType('null', $this->response->getHeader('test_unit'));
    }
}