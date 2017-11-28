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

namespace Tests\Unit;

use \PHPUnit\Framework\TestCase;


class ControllerTest extends TestCase
{
    /** @test  */
    public function testFirstMethod()
    {
        $num = 20;

        $this->assertEquals(20, $num);
    }
}