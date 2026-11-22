<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'AlphaKit'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool)env('APP_DEBUG', false),
    'version' => '1.0.0',
    'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    'url' => env('APP_URL', 'http://127.0.0.1:8000'),

    // 服务提供者(执行顺序:先全部 register 再全部 boot)
    'providers' => [
        // App\Providers\AppServiceProvider::class,
    ],

    // 全局中间件(所有路由生效)
    'global_middleware' => [
        // \App\Middleware\TraceRequests::class,
    ],

    // 中间件别名 => 类名(路由中使用别名)
    'middleware_aliases' => [
        'auth' => \App\Middleware\Authenticate::class,
        'session' => \AlphaKit\Core\Middleware\StartSession::class,
        'throttle' => \App\Middleware\ThrottleRequests::class,
    ],

    // 应用自定义命令
    'commands' => [
        \App\Commands\AppInfoCommand::class,
        \App\Commands\TaskStatsCommand::class,
    ],

    // 路由文件(相对项目根)
    'route_files' => [
        'routes/web.php',
        'routes/api.php',
    ],
];
