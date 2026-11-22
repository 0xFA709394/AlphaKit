<?php

declare(strict_types=1);

namespace App\Middleware;

use AlphaKit\Core\Exception\HttpException;
use AlphaKit\Core\Middleware\MiddlewareInterface;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * 限流中间件(基于缓存计数;throttle:次数,窗口秒)
 */
final class ThrottleRequests implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, int $max = 60, int $decay = 60): Response
    {
        $key = 'throttle:' . sha1($request->ip() . '|' . $request->path());
        $cache = cache_store();

        $hits = (int)$cache->get($key, 0) + 1;
        $cache->set($key, $hits, $decay);

        if ($hits > $max) {
            throw new HttpException(429, '请求过于频繁,请稍后再试');
        }

        return $next($request)->withHeader('X-RateLimit-Remaining', (string)max(0, $max - $hits));
    }
}
