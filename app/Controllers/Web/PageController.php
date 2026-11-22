<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Core\Session;

/**
 * Web 页面控制器(视图 + Session 演示)
 */
final class PageController extends Controller
{
    /** GET / 首页 */
    public function welcome(Request $request): Response
    {
        return $this->render('welcome', [
            'appName' => (string)config('app.name'),
            'phpVersion' => PHP_VERSION,
            'features' => [
                ['RESTful API', '路由 / 中间件 / JWT 认证 / 统一响应结构'],
                ['Web 页面', 'PHP 原生模板 / 布局继承 / Session'],
                ['数据层', 'PDO 封装 / 查询构造器 / Active Record / 迁移'],
                ['异步任务', '数据库队列 / 重试 / 失败记录'],
                ['定时任务', 'cron 表达式调度器'],
                ['CLI 工具', '20+ 内置命令 / 代码生成器'],
            ],
        ]);
    }

    /** GET /docs 能力总览 */
    public function docs(): Response
    {
        return $this->render('docs', [
            'routes' => $this->sampleRoutes(),
        ]);
    }

    /** GET /demo/session Session 计数演示 */
    public function sessionDemo(Request $request): Response
    {
        /** @var Session $session */
        $session = app(Session::class);

        $visits = (int)$session->get('visits', 0) + 1;
        $session->put('visits', $visits);

        if ($visits === 1) {
            $session->flash('hello', '欢迎首次访问!');
        }

        return $this->render('session', [
            'visits' => $visits,
            'flash' => (string)$session->getFlash('hello', ''),
            'sessionId' => substr($session->id(), 0, 8),
        ]);
    }

    /** @return array<int, array{method: string, path: string, desc: string}> */
    private function sampleRoutes(): array
    {
        return [
            ['method' => 'GET', 'path' => '/api/health', 'desc' => '健康检查'],
            ['method' => 'POST', 'path' => '/api/auth/register', 'desc' => '注册(队列发欢迎邮件)'],
            ['method' => 'POST', 'path' => '/api/auth/login', 'desc' => '登录获取 JWT'],
            ['method' => 'GET', 'path' => '/api/me', 'desc' => '当前用户(需 Bearer Token)'],
            ['method' => 'GET', 'path' => '/api/tasks', 'desc' => '任务列表(分页/过滤)'],
            ['method' => 'POST', 'path' => '/api/tasks', 'desc' => '创建任务'],
            ['method' => 'GET', 'path' => '/demo/session', 'desc' => 'Session 计数演示'],
            ['method' => 'CLI', 'path' => 'php bin/alphakit', 'desc' => '命令行工具入口'],
        ];
    }
}
