<?php

use Illuminate\Broadcasting\BroadcastManager;

return [
    'token' => env('TELEGRAM_BOT_TOKEN'),
    'name' => env('TELEGRAM_BOT_USERNAME', env('TELEGRAM_BOT_NAME')),
    'webapp_url' => env('TELEGRAM_WEB_APP_URL', env('TELEGRAM_WEBAPP_URL')),
    'providers' => [
        BroadcastManager::class,
    ],
    'channel_authentication' => false,
    'secret_token' => env('TELEGRAM_WEBHOOK_SECRET_TOKEN'),
    'channels' => [
        'private' => [
            'encryption' => true,
        ],
    ],
];
