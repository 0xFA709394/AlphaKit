<?php

declare(strict_types=1);

namespace App\Middleware;

use AlphaKit\Auth\AuthManager;
use AlphaKit\Core\Exception\AuthenticationException;
use AlphaKit\Core\Middleware\MiddlewareInterface;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * JWT 认证中间件
 * 校验 Authorization: Bearer <token>,通过后把用户注入请求
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(protected AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->authenticate($request);

        // 注入当前用户(控制器中 $request->user 可用)
        $request->user = $user;

        return $next($request);
    }
}
