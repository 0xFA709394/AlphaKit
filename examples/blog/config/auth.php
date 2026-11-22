<?php

declare(strict_types=1);

return [
    'table' => 'users',
    'jwt' => [
        'secret' => env('JWT_SECRET', 'blog-sample-secret'),
        'ttl' => env('JWT_TTL', 7200),
    ],
];
