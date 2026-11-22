<?php

declare(strict_types=1);

namespace App\Middleware;

use AlphaKit\Core\Middleware\MiddlewareInterface;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * 全局中间件:为每个响应附加唯一请求 ID(后置逻辑演示)
 */
final class RequestId implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Request-Id', bin2hex(random_bytes(8)));
    }
}
