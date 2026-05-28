<?php

return [
    'default_guard' => 'web',

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
    ],

    'providers' => [
        'users' => [
            'driver'         => 'orm',
            'model'          => \App\Models\Users::class,
            'username_field' => 'email',
        ],
    ],

    'hashing' => [
        'algo'        => PASSWORD_ARGON2ID,
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 1,
    ],

    'login_url' => '/login',
    'home_url'  => '/',

    'throttle' => ['max' => 5, 'decay' => 60],
];
