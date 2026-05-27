<?php


return [

    'silver_key' => env('APP_KEY', '__Your_Secret_Key_Go_Here__'),

    'terminal' => [
        'online' => false,
        'username' => 'debug_4785478652628',
        'password' => '4jh5g25gjh2g5k4g5hj2gj45h2g5g2j5gjk2ghj2',
    ],

    'debug' => true,

    'paths' => [
        'App'      => 'app/',
        'Database' => 'database/',
        'Storage'  => 'storage/',
        'Packages' => 'packages/',
        'Public'   => 'Public/',
    ],

    'datetime' => [
        'format'      => 'mm-dd-Y h:i:s',
        'region'      => 'Europe/Ljubljana',
        'time_format' => 24,
    ],

    'date' => [
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i:s',
        'timezone'    => 'UTC',
    ],

    'caches' => [
        'enabled'     => false,
        'expire_time' => 1,
    ],

    'cookies'=>[
        'encrypted' => 1
    ],

];