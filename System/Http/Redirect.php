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

namespace Silver\Http;

use Silver\Exception;

/**
 *
 */
class Redirect
{
	public static function to($url)
    {
        return Response::instance()->redirect($url);
	}

	public static function back($fallback = null)
	{
        if(isset($_SERVER['HTTP_REFERER'])) {
            return self::to($_SERVER['HTTP_REFERER']);
        } else {
            if ($fallback !== null) {
                return self::to($fallback);
            } else {
                throw new Exception("Unknow referer.");
            }
        }
	}
}
