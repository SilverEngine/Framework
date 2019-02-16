<?php

namespace Tests\Unit\Framework\Http;

use \PHPUnit\Framework\TestCase;
use Silver\Http\Session;

class SessionsTest extends TestCase
{
    protected $session;

    public function setUp()
    {
        $this->session = new Session();
    }

    public function testSetSession()
    {
        $this->assertEquals($this->session->set('test_session', 'works'), 'works');
    }

    public function testGetSession()
    {
        $this->session->set('test_session', 'works');

        $this->assertEquals($this->session->get('test_session'), 'works');
    }

    public function testFlashSession()
    {
        $this->assertEquals($this->session->flash('test_session', 'works'), 'works');
    }

    public function testIfNotExistTheSession()
    {
        $this->assertEquals($this->session->exists('notexists'), false);
    }

    public function testIfExistTheSession()
    {
        $this->assertEquals($this->session->exists('test_session'), true);
    }

    public function testDeleteSession()
    {
        $this->assertEquals($this->session->delete('test_session'), null);
    }

}
