<?php

use App\Providers\TelegramServiceProvider;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'Enclave CRM'),
    'author' => env('APP_AUTHOR', 'AidSoul <work-aidsoul@outlook.com>'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'UTC',
    'locale' => 'ru',
    'fallback_locale' => 'ru',
    'faker_locale' => 'ru_RU',
    'currency' => env('APP_CURRENCY', 'BYN'),
    'currency_symbol' => env('APP_CURRENCY_SYMBOL', 'Br'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => 'file',
    ],
    'providers' => [
        ...ServiceProvider::defaultProviders()->toArray(),
        TelegramServiceProvider::class,
    ],
];
