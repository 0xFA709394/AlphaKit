<?php
/**
 * AlphaKit 全局助手函数
 */

declare(strict_types=1);

use AlphaKit\Core\Application;
use AlphaKit\Core\Config;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Validation\Validator;

if (!function_exists('app')) {
    /**
     * 获取应用实例或从容器解析服务
     *
     * @param string|null $abstract 类名或服务名
     * @return Application|mixed
     */
    function app(?string $abstract = null)
    {
        $instance = Application::getInstance();
        return $abstract === null ? $instance : $instance->make($abstract);
    }
}

if (!function_exists('config')) {
    /**
     * 获取配置(点号语法),可设默认值
     *
     * @param string|null $key     如 "database.default"
     * @param mixed       $default 默认值
     * @return mixed|Config
     */
    function config(?string $key = null, $default = null)
    {
        $config = app(Config::class);
        if ($key === null) {
            return $config;
        }
        return $config->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * 读取环境变量(带默认值)
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            return $_ENV[$key] ?? $default;
        }
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }
        return $value;
    }
}

if (!function_exists('base_path')) {
    /** 项目根目录 */
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('storage_path')) {
    /** storage 目录 */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . $path : ''));
    }
}

if (!function_exists('database_path')) {
    /** database 目录 */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path !== '' ? '/' . $path : ''));
    }
}

if (!function_exists('config_path')) {
    /** config 目录 */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path !== '' ? '/' . $path : ''));
    }
}

if (!function_exists('public_path')) {
    /** public 目录 */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path !== '' ? '/' . $path : ''));
    }
}

if (!function_exists('e')) {
    /** HTML 转义输出 */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('response')) {
    /** 构造响应 */
    function response($body = '', int $status = 200, array $headers = []): Response
    {
        return new Response($body, $status, $headers);
    }
}

if (!function_exists('json_response')) {
    /** JSON 响应 */
    function json_response($data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }
}

if (!function_exists('url')) {
    /**
     * 命名路由 URL 生成
     *
     * url('post.show', ['id' => 5]); // -> /posts/5
     * 可选参数未提供时整段移除
     */
    function url(string $name, array $params = []): string
    {
        return app(\AlphaKit\Core\Router::class)->url($name, $params);
    }
}

if (!function_exists('view')) {
    /**
     * 渲染视图
     *
     * @param string $template 视图名,如 "tasks/index"
     * @param array  $data     传给视图的数据
     */
    function view(string $template, array $data = []): Response
    {
        return app(\AlphaKit\View\ViewEngine::class)->render($template, $data);
    }
}

if (!function_exists('abort')) {
    /** 抛出 HTTP 异常 */
    function abort(int $status, string $message = ''): void
    {
        throw new \AlphaKit\Core\Exception\HttpException($status, $message);
    }
}

if (!function_exists('validator')) {
    /**
     * 创建验证器
     *
     * @param array $data  待验证数据
     * @param array $rules 验证规则
     */
    function validator(array $data, array $rules): Validator
    {
        return new Validator($data, $rules);
    }
}

if (!function_exists('dispatch')) {
    /**
     * 将任务派发到队列(异步执行)
     *
     * @param object $job 需实现 handle() 方法
     */
    function dispatch(object $job): void
    {
        app(\AlphaKit\Queue\QueueManager::class)->push($job);
    }
}

if (!function_exists('logger')) {
    /** @var array<string, \AlphaKit\Log\Logger> 日志通道实例池 */
    $GLOBALS['__alphakit_loggers'] = [];

    /** 获取日志实例(按通道名缓存) */
    function logger(?string $channel = null): \AlphaKit\Log\Logger
    {
        $name = $channel ?? 'app';
        return $GLOBALS['__alphakit_loggers'][$name] ??= new \AlphaKit\Log\Logger($name);
    }
}

if (!function_exists('cache_store')) {
    /** 获取缓存存储实例(默认驱动) */
    function cache_store(?string $name = null): \AlphaKit\Cache\Store
    {
        /** @var \AlphaKit\Cache\CacheManager $manager */
        $manager = app(\AlphaKit\Cache\CacheManager::class);
        return $manager->store($name);
    }
}

if (!function_exists('now')) {
    /** 当前时间字符串(Y-m-d H:i:s) */
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('dd')) {
    /** 调试输出并终止 */
    function dd(...$vars): void
    {
        foreach ($vars as $v) {
            echo '<pre style="background:#111;color:#0f0;padding:12px;border-radius:6px;overflow:auto">'
                . htmlspecialchars(var_export($v, true), ENT_QUOTES, 'UTF-8')
                . '</pre>';
        }
        exit(1);
    }
}
