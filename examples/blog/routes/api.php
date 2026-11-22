<?php

declare(strict_types=1);

/**
 * API 路由(JWT/资源路由/缓存/限流/HttpClient 演示)
 * 注意:具名精确路由(如 /hot)需在 resource({id} 无约束)之前注册
 */

use AlphaKit\Core\Response;
use AlphaKit\Core\Router;
use App\Controllers\Api\{AuthController, DemoController, PostController, TagController};

return function (Router $router): void {
    $router->group(['prefix' => '/api', 'middleware' => ['timed:300']], function (Router $router): void {

        // 健康检查(relay 的上游)
        $router->get('/health', fn () => Response::ok([
            'status' => 'up', 'app' => config('app.name'), 'time' => date('c'),
        ]))->name('api.health');

        // 框架能力演示(公开):text 响应 / 日志级别 / 一次性令牌 / HttpClient POST
        $router->get('/demo/text', [DemoController::class, 'text']);
        $router->get('/demo/log-levels', [DemoController::class, 'logLevels']);
        $router->get('/demo/one-time-token', [DemoController::class, 'issueToken']);
        $router->post('/demo/one-time-token', [DemoController::class, 'consumeToken']);
        $router->post('/demo/relay-post', [DemoController::class, 'relayPost']);

        // 认证(无需登录)
        $router->post('/auth/register', [AuthController::class, 'register']);
        $router->post('/auth/login', [AuthController::class, 'login']);

        $router->group(['middleware' => ['auth']], function (Router $router): void {
            $router->get('/me', [AuthController::class, 'me']);
            $router->post('/token/refresh', [DemoController::class, 'refresh']);

            // 具名精确路由(先于 resource,防 {id} 吞噬)
            $router->get('/posts/hot', [PostController::class, 'hot']);
            $router->get('/posts/{id:\d+}/export', [PostController::class, 'export'])->name('api.post.export');
            $router->post('/posts/{id:\d+}/view', [PostController::class, 'view']);
            $router->post('/posts/{id:\d+}/comments', [PostController::class, 'comment']);

            // Tag 高级查询演示(先于 resource)
            $router->get('/tags/filter', [TagController::class, 'filter']);
            $router->post('/tags/bulk', [TagController::class, 'bulk']);
            $router->post('/tags/sync', [TagController::class, 'sync']);
            $router->post('/tags/warm', [TagController::class, 'warm']);

            // 限流参数演示:30 次 / 60 秒
            $router->post('/relay/health', [PostController::class, 'relay'])->middleware('throttle:30,60');
            // 强限流演示:2 次 / 60 秒(第 3 次触发 429)
            $router->get('/demo/limited', [DemoController::class, 'text'])->middleware('throttle:2,60');

            // RESTful 资源路由(注册在具名路由之后)
            $router->resource('/posts', PostController::class);
            $router->resource('/tags', TagController::class);
        });
    });
};
