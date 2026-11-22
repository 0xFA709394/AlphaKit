<?php

declare(strict_types=1);

/**
 * API 路由(JWT/资源路由/缓存/限流/HttpClient 演示)
 * 注意:具名精确路由(如 /hot)需在 resource({id} 无约束)之前注册
 */

use AlphaKit\Core\Response;
use AlphaKit\Core\Router;
use App\Controllers\Api\{AuthController, PostController};

return function (Router $router): void {
    $router->group(['prefix' => '/api', 'middleware' => ['timed:300']], function (Router $router): void {

        // 健康检查(relay 的上游)
        $router->get('/health', fn () => Response::ok([
            'status' => 'up', 'app' => config('app.name'), 'time' => date('c'),
        ]))->name('api.health');

        // 认证(无需登录)
        $router->post('/auth/register', [AuthController::class, 'register']);
        $router->post('/auth/login', [AuthController::class, 'login']);

        $router->group(['middleware' => ['auth']], function (Router $router): void {
            $router->get('/me', [AuthController::class, 'me']);

            // 具名精确路由(先于 resource,防 {id} 吞噬)
            $router->get('/posts/hot', [PostController::class, 'hot']);
            $router->get('/posts/{id:\d+}/export', [PostController::class, 'export'])->name('api.post.export');
            $router->post('/posts/{id:\d+}/view', [PostController::class, 'view']);
            $router->post('/posts/{id:\d+}/comments', [PostController::class, 'comment']);

            // 限流参数演示:30 次 / 60 秒
            $router->post('/relay/health', [PostController::class, 'relay'])->middleware('throttle:30,60');

            // RESTful 资源路由(注册在具名路由之后)
            $router->resource('/posts', PostController::class);
        });
    });
};
