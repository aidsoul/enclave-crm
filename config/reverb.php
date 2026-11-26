<?php

return [
    'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
    'port' => env('REVERB_PORT', 443),
    'scheme' => env('REVERB_SCHEME', 'https'),
    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    'certificates' => [
        'local' => env('REVERB_LOCAL_CERT'),
        'passphrase' => env('REVERB_LOCAL_PASSPHRASE'),
    ],
    'socket_id' => null,
    'logging' => env('REVERB_LOGGING', false),
    'options' => [
        'max_message_size' => env('REVERB_MAX_MESSAGE_SIZE', 10240),
        'message_queue' => env('REVERB_MESSAGE_QUEUE', 'default'),
    ],
    'apps' => [
        'provider' => 'config',
        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'options' => [
                    'host' => env('REVERB_HOST', '127.0.0.1'),
                    'port' => env('REVERB_PORT', 6001),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                ],
                'allowed_origins' => env('APP_ENV', 'production') === 'production'
                    ? array_values(array_filter([
                        parse_url((string) env('APP_URL'), PHP_URL_HOST),
                        'web.telegram.org',
                    ]))
                    : ['*'],
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],
    ],
];
