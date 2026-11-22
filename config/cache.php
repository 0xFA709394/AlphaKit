<?php

declare(strict_types=1);

return [
    // 默认驱动:file / redis / array
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache/data'),
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => 0,
            'prefix' => 'alphakit:',
        ],
        'array' => [
            'driver' => 'array',
        ],
    ],
];
