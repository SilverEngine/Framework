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

    public function __construct()
    {
        // Unified shared data: available in classic Ghost views ($appName)
        // AND as a Wisp prop on every page.
        View::share('appName', 'SilverEngine');

        // Composer: injected only when the "Welcome" view/Wisp component renders.
        View::composer('Welcome', fn(): array => [
            'serverTime' => date('Y-m-d H:i:s'),
        ]);
    }

    public function welcome()
    {
        return View::demo();
    }

    public function demo()
    {
        $data = [];
        return View::make('welcome')->withComponent($data);
    }

    public function wisp()
    {
        // appName comes from View::share(), serverTime from the composer —
        // only `message` and the deferred `stats` are page-specific props.
        return wisp('Welcome', [
            'message' => 'Wisp is running — server-driven Vue, baked into Ghost.',
            // Deferred prop: excluded from first paint, auto-fetched by the
            // client after mount (and prefetchable via <Link prefetch>).
            'stats'   => \Silver\Engine\Ghost\Wisp::defer(
                fn() => ['routes' => count(\Silver\Core\Route::all())],
            ),
        ]);
    }
}
