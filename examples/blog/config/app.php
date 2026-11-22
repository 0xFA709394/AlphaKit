<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'AlphaBlog'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool)env('APP_DEBUG', false),
    'version' => '1.0.0',
    'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),

    // ★ 服务提供者:register(容器绑定) + boot(事件订阅) 全链路演示
    'providers' => [
        \App\Providers\AppServiceProvider::class,
    ],

    'global_middleware' => [
        // 全局中间件演示:每个响应附加 X-Request-Id
        \App\Middleware\RequestId::class,
    ],

    'middleware_aliases' => [
        'auth' => \App\Middleware\Authenticate::class,
        'session' => \AlphaKit\Core\Middleware\StartSession::class,
        'throttle' => \App\Middleware\ThrottleRequests::class,
        // 洋葱模型完整演示:前置计时 + 后置记录耗时
        'timed' => \App\Middleware\TimedLog::class,
    ],

    'commands' => [
        \App\Commands\BlogStatsCommand::class,
        \App\Commands\PostsPublishCommand::class,
        \App\Commands\PostsCleanupCommand::class,
        \App\Commands\TagsSyncCommand::class,
    ],

    'route_files' => [
        'routes/web.php',
        'routes/api.php',
    ],
];
