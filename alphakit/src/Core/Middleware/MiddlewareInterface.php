<?php

declare(strict_types=1);

namespace AlphaKit\Core\Middleware;

use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * 中间件契约(洋葱模型)
 *
 * 实现:
 *   public function handle(Request $request, callable $next): Response
 */
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
