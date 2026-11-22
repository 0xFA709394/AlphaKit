<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * 演示接口:健康检查 / 限流示例
 */
final class DemoController extends Controller
{
    /** GET /api/health */
    public function health(): Response
    {
        return $this->ok([
            'status' => 'up',
            'app' => config('app.name'),
            'version' => config('app.version'),
            'php' => PHP_VERSION,
            'time' => date('c'),
        ]);
    }

    /** GET /api/demo/throttle (限流中间件演示:60 次/60 秒) */
    public function throttle(Request $request): Response
    {
        return $this->ok(['message' => '请求成功(限流: 60次/分钟)']);
    }
}
