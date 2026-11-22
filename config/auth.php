<?php

declare(strict_types=1);

return [
    // 用户表
    'table' => 'users',

    'jwt' => [
        // 密钥(php bin/alphakit key:generate 生成)
        'secret' => env('JWT_SECRET', 'change-me-in-production'),
        // 令牌有效期(秒),默认 2 小时
        'ttl' => env('JWT_TTL', 7200),
    ],
];
