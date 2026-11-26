<?php

return [
    'telegram' => [
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'init_data_ttl' => (int) env('TELEGRAM_INIT_DATA_TTL', 86400),
    ],
];
