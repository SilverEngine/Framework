<?php

/*
|--------------------------------------------------------------------------
| # Environment master settings
|--------------------------------------------------------------------------
*/

return [
    'debug'         => true,
    'app_key'       => 'mysupersecurekey',


    'middlewares'   => [

    ],

    'databases' => [
        'on' => true,
        'default' => 'local',
        'local'   => [
            'service'       => true,
            'driver'        => 'mysql',
            'hostname'      => 'localhost',
            'username'      => 'silver',
            'password'      => 'secret',
            'basename'      => 'youtdatabase',
            'limit_request' => 25,
        ]
    ],

    'mail' => [
        'service' => false,
        'email'   => 'your@email.test',
        'name'    => 'Your Name',
    ],

];