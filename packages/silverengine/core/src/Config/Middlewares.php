<?php


return [
    10 => Silver\App\Middlewares\ErrorHandler::class,
    15 => Silver\Http\Middleware\VerifyCsrfToken::class,
    20 => Silver\App\Middlewares\AccessLog::class,
    25 => App\Middlewares\Wisp::class,
    30 => Silver\App\Middlewares\Version::class,
    40 => App\Middlewares\Auth::class,
];
