<?php

use App\Models\User;

return [

    'defaults' => [
        'guard' => 'tma',
        'passwords' => 'tma_users',
    ],

    'guards' => [
        'tma' => [
            'driver' => 'session',
            'provider' => 'tma_users',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'tma_users',
            'hash' => false,
        ],
    ],

    'providers' => [
        'tma_users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [
        'tma_users' => [
            'provider' => 'tma_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
