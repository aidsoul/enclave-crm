<?php

return [

    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('APP_URL'),
        'https://web.telegram.org',
    ]),

    'allowed_origins_patterns' => array_merge(
        ['#^https?://([a-zA-Z0-9-]+\.)*telegram\.org$#'],
        env('APP_ENV') === 'production' ? [] : [
            '#^https?://localhost(:[0-9]+)?$#',
            '#^https?://127\.0\.0\.1(:[0-9]+)?$#',
        ]
    ),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
