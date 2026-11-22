<?php

declare(strict_types=1);

return [
    'default' => env('CACHE_DRIVER', 'file'),
    'stores' => [
        'file' => ['driver' => 'file', 'path' => storage_path('cache/data')],
        'array' => ['driver' => 'array'],
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'prefix' => 'alphablog:',
        ],
    ],
];
