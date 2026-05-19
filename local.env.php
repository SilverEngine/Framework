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

    'routes' => [
      
    ],

    'databases' => [
        'on' => true,
        'default' => 'local',
        'local'   => [
            'service'       => true,
            'driver'        => 'sqlite',
            'database'      => 'Database/db.sqlite',
            'hostname'      => 'localhost',
            'username'      => '',
            'password'      => '',
            'basename'      => 'test1',
            'limit_request' => 25,
        ]
    ],

    'mail' => [
        'service' => false,
        'email'   => 'your@email.test',
        'name'    => 'Your Name',
    ],

];
