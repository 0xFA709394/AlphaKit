<?php

declare(strict_types=1);

namespace App\Middleware;

use AlphaKit\Auth\AuthManager;
use AlphaKit\Core\Middleware\MiddlewareInterface;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * JWT 认证中间件(构造函数依赖注入演示)
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(protected AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $request->user = $this->auth->authenticate($request);
        return $next($request);
    }
}
