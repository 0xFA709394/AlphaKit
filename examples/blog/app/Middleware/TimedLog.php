<?php

declare(strict_types=1);

namespace App\Middleware;

use AlphaKit\Core\Middleware\MiddlewareInterface;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * 洋葱模型完整演示:前置计时 + 后置记录耗时(中间件参数演示:timed:阈值毫秒)
 */
final class TimedLog implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, int $slowMs = 200): Response
    {
        $start = microtime(true);            // 前置
        $response = $next($request);         // 洋葱中心
        $ms = (int)round((microtime(true) - $start) * 1000);  // 后置

        if ($ms >= $slowMs) {
            logger('slow')->warning('慢请求', ['path' => $request->path(), 'ms' => $ms, 'threshold' => $slowMs]);
        }
        return $response->withHeader('X-Elapsed-Ms', (string)$ms);
    }
}
