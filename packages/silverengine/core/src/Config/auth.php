<?php

return [
    'default_guard' => 'web',

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        // Stateless bearer tokens (Authorization: Bearer selector:validator).
        // Requires the api_tokens table — see ApiTokenRepository docblock.
        'api' => ['driver' => 'token', 'provider' => 'users', 'table' => 'api_tokens'],
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

    // Remember-me (session guards). Requires the remember_tokens table:
    //   selector (string, PK) / token_hash (string) /
    //   user_id (unsignedBigInt, index) / expires_at (bigInt).
    'remember' => [
        'enabled' => true,
        'cookie'  => 'remember_token',
        'table'   => 'remember_tokens',
        'ttl'     => 2_592_000, // 30 days
    ],

    'throttle' => ['max' => 5, 'decay' => 60],
];
