<?php

declare(strict_types=1);

namespace AlphaKit\Core\Middleware;

use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Core\Session;

/**
 * Session 启动中间件
 * 前置:恢复会话;后置:持久化并附加 cookie
 */
class StartSession implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $session = app(Session::class);
        $session->start($request);

        $response = $next($request);

        return $session->save($response);
    }
}
