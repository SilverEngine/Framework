<?php
declare(strict_types=1);

return [
    'on'      => filter_var(env('DB_ON', false), FILTER_VALIDATE_BOOLEAN),
    'default' => env('DB_CONNECTION', 'local'),
    'local'   => [
        'service'       => true,
        'driver'        => env('DB_DRIVER', 'mysql'),
        'database'      => env('DB_DATABASE', ''),
        'hostname'      => env('DB_HOST', 'localhost'),
        'port'          => env('DB_PORT', '3306'),
        'username'      => env('DB_USERNAME', 'root'),
        'password'      => env('DB_PASSWORD', ''),
        'basename'      => env('DB_DATABASE', ''),
        'limit_request' => (int) env('DB_LIMIT_REQUEST', 25),
    ],
];
