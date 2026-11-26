<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 180),
            'block_for' => null,
            'after_commit' => false,
        ],
        'redis-low' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'low',
            'retry_after' => (int) env('QUEUE_RETRY_AFTER_LOW', 1850),
            'block_for' => null,
            'after_commit' => false,
        ],
    ],
    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
