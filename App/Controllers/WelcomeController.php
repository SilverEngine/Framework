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

namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Http\View;

class WelcomeController extends Controller
{
	  private $model_name = false;
    private $table = false;

    public function welcome()
    {
        return View::demo();
    }

    public function index()
    {
				$portfolio = [
					[
						'id' => '1',
						'title' => 'Stationary',
						'description' => 'A yellow pencil with envelopes on a clean, blue backdrop!',
					],
					[
						'id' => '2',
						'title' => 'Ice Cream',
						'description' => 'A dark blue background with a colored pencil, a clip, and a tiny ice cream cone',
					],
					[
						'id' => '3',
						'title' => 'Strawberries',
						'description' => 'Strawberries are such a tasty snack, especially with a little sugar on top!',
					],
					[
						'id' => '4',
						'title' => 'Workspace',
						'description' => 'A yellow workspace with some scissors, pencils, and other objects.',
					],
				];
        return View::make('welcome')->withComponent($portfolio);
    }
}
