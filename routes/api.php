<?php

declare(strict_types=1);

/**
 * API 路由(JWT 认证 + 资源 CRUD + 队列/缓存演示)
 */

use App\Controllers\Api\AuthController;
use App\Controllers\Api\DemoController;
use App\Controllers\Api\TaskController;
use AlphaKit\Core\Router;

return function (Router $router): void {
    $router->group(['prefix' => '/api'], function (Router $router): void {
        // 健康检查
        $router->get('/health', [DemoController::class, 'health'])->name('api.health');

        // 认证(无需登录)
        $router->post('/auth/register', [AuthController::class, 'register']);
        $router->post('/auth/login', [AuthController::class, 'login']);

        // 限流演示:60 次/分钟(基于缓存计数)
        $router->get('/demo/throttle', [DemoController::class, 'throttle'])->middleware('throttle:60,60');

        // 需要登录的接口
        $router->group(['middleware' => ['auth']], function (Router $router): void {
            $router->get('/me', [AuthController::class, 'me']);
            $router->post('/auth/logout', [AuthController::class, 'logout']);

            // 任务 CRUD(资源路由)
            $router->resource('/tasks', TaskController::class);

            // 关联演示:某用户的任务
            $router->get('/users/{id:\d+}/tasks', [TaskController::class, 'forUser']);
        });
    });
};
