<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Excluded Routes / Actions
    |--------------------------------------------------------------------------
    |
    | Patterns for route names, URIs, and controller actions to exclude
    | from generated Wayfinder definitions.
    |
    */
    'exclude' => [
        'routes' => [
            'horizon.*',
        ],
        'uris' => [
            'horizon*',
            'broadcasting/*',
            'api/telegram/webhook',
            'api/orders/incoming',
            'api/leads/incoming',
            'api/catalog/*',
        ],
        'controllers' => [
            'Laravel\Horizon\*',
            'Illuminate\Broadcasting\*',
            'App\Http\Controllers\TelegramController',
            'App\Http\Controllers\IncomingOrderController',
            'App\Http\Controllers\IncomingLeadController',
            'App\Http\Controllers\CatalogSyncController',
        ],
    ],
];
