<?php

declare(strict_types=1);

namespace AlphaKit\Core;

use AlphaKit\Core\Event\EventDispatcher;
use AlphaKit\Core\Exception\HttpException;
use AlphaKit\Core\Exception\NotFoundException;
use AlphaKit\Core\Middleware\Pipeline;
use Throwable;

/**
 * 应用内核
 *
 * 职责:
 * 1. 引导:加载 .env / 配置 / 服务提供者
 * 2. HTTP 内核:路由匹配 -> 中间件管道 -> 控制器 -> 异常兜底
 * 3. 提供单例容器入口
 *
 * 用法:
 *   $app = Application::getInstance();       // 引导
 *   $app->runHttp();                          // 处理 HTTP(内置服务器/FPM)
 *   $app->runConsole($argv);                  // 处理 CLI(见 bin/alphakit)
 */
class Application extends Container
{
    protected static ?Application $instance = null;

    /** @var bool 是否已引导 */
    protected bool $booted = false;

    /** @var array<string, string> 中间件别名 => 类名 */
    protected array $middlewareAliases = [];

    /** @var array<string> 全局中间件 */
    protected array $globalMiddleware = [];

    /** @var ServiceProvider[] */
    protected array $providers = [];

    protected function __construct(protected string $basePath)
    {
        // 尽早暴露单例,保证构造期间 app() 可用
        self::$instance = $this;
        $this->instance(self::class, $this);
        $this->boot();
    }

    public static function getInstance(string $basePath = BASE_PATH): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }
        return self::$instance;
    }

    /** 引导应用 */
    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        Dotenv::load($this->basePath . '/.env');

        // 核心绑定
        $this->instance(Container::class, $this);
        $this->singleton(Config::class);
        $this->singleton(Router::class);
        $this->singleton(Session::class);
        $this->singleton(EventDispatcher::class);
        $this->singleton(\AlphaKit\Log\Logger::class);
        $this->singleton(\AlphaKit\View\ViewEngine::class);
        $this->singleton(\AlphaKit\Database\Connection::class);
        $this->singleton(\AlphaKit\Database\Migrator::class);
        $this->singleton(\AlphaKit\Cache\CacheManager::class);
        $this->singleton(\AlphaKit\Queue\QueueManager::class);
        $this->singleton(\AlphaKit\Schedule\Scheduler::class);
        $this->singleton(\AlphaKit\Auth\AuthManager::class);

        // 错误与异常兜底
        $this->registerErrorHandling();

        // 加载应用服务提供者
        $providers = (array)$this->make(Config::class)->get('app.providers', []);
        foreach ($providers as $providerClass) {
            /** @var ServiceProvider $provider */
            $provider = new $providerClass($this);
            $provider->register();
            $this->providers[] = $provider;
        }
        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        // 加载路由文件
        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        $router = $this->make(Router::class);
        $config = $this->make(Config::class);

        // 中间件别名与全局中间件
        $this->middlewareAliases = (array)$config->get('app.middleware_aliases', []);
        $this->globalMiddleware = (array)$config->get('app.global_middleware', []);

        foreach ((array)$config->get('app.route_files', ['routes/web.php', 'routes/api.php']) as $file) {
            $path = $this->basePath . '/' . $file;
            if (is_file($path)) {
                $routerLoad = require $path;
                if ($routerLoad instanceof \Closure) {
                    $routerLoad($router);
                }
            }
        }
    }

    protected function registerErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        // 时区:config/app.php 优先,回退 .env 与默认值
        $timezone = (string)config('app.timezone', env('APP_TIMEZONE', 'Asia/Shanghai'));
        date_default_timezone_set(is_string($timezone) && $timezone !== '' ? $timezone : 'Asia/Shanghai');

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            // 被 @ 抑制的错误直接吞掉
            if (!(error_reporting() & $severity)) {
                return true;
            }
            // 废弃告警不升级为异常(PHP 8.x 下第三方/用户代码大量触发,升级会误伤正常流程)
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                return true;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            $this->renderException($e, Request::capture())->send();
        });
    }

    // ---------------------------------------------------------------- HTTP

    /**
     * 处理 HTTP 请求并发送响应
     */
    public function runHttp(): void
    {
        $request = Request::capture();
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * 处理请求(不发送,便于测试)
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->dispatchToRouter($request);
        } catch (Throwable $e) {
            return $this->renderException($e, $request);
        }
    }

    protected function dispatchToRouter(Request $request): Response
    {
        /** @var Router $router */
        $router = $this->make(Router::class);

        $matched = $router->match($request->method, $request->path());
        if ($matched === null) {
            throw new NotFoundException('路由不存在: ' . $request->path());
        }

        $request->params = $matched['params'];

        // 中间件:全局 + 路由(别名解析)
        $middleware = array_merge(
            $this->globalMiddleware,
            array_map(fn ($m) => $this->resolveMiddleware($m), $matched['middleware'])
        );

        /** @var Pipeline $pipeline */
        $pipeline = $this->make(Pipeline::class);

        return $pipeline->through($middleware)->then($request, function () use ($matched, $request): Response {
            return $this->invokeHandler($matched['handler'], $request);
        });
    }

    /** 执行控制器/闭包 */
    protected function invokeHandler($handler, Request $request): Response
    {
        if ($handler instanceof \Closure) {
            $result = $handler($request);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = $this->make($class);
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("控制器 [$class::$method] 不存在");
            }
            $result = $controller->{$method}($request);
        } else {
            throw new \RuntimeException('不支持的路由处理器类型');
        }

        if ($result instanceof Response) {
            return $result;
        }
        // 数组/标量自动转 JSON
        return Response::json($result);
    }

    /** 别名解析: "auth" / "throttle:60,60" => 完整类名(参数保留) */
    protected function resolveMiddleware(string $name): string
    {
        // 带参数:先拆出别名再回拼
        $pos = strpos($name, ':');
        if ($pos !== false) {
            $alias = substr($name, 0, $pos);
            $parameters = substr($name, $pos + 1);
            return ($this->middlewareAliases[$alias] ?? $alias) . ':' . $parameters;
        }
        return $this->middlewareAliases[$name] ?? $name;
    }

    /** 异常 -> 响应(JSON / HTML) */
    public function renderException(Throwable $e, Request $request): Response
    {
        try {
            logger()->error($e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'trace' => $e->getTraceAsString()]);
        } catch (Throwable) {
            // 日志失败不影响响应
        }

        $status = $e instanceof HttpException ? $e->getCode() : 500;
        $message = $e->getMessage();

        if ($status >= 500 && !$this->isDebug()) {
            $message = '服务器内部错误';
        }

        if ($request->expectsJson() || PHP_SAPI === 'cli') {
            $errors = $e instanceof Exception\ValidationException ? $e->errors : null;
            return Response::fail($message, $status, $status, $errors);
        }

        $detail = $this->isDebug()
            ? '<p><small>' . e($e->getFile() . ':' . $e->getLine()) . '</small></p><pre style="text-align:left;background:#f6f6f6;padding:12px;border-radius:6px;overflow:auto">' . e($e->getTraceAsString()) . '</pre>'
            : '';
        return Response::html(
            '<!doctype html><html lang="zh"><meta charset="utf-8"><title>' . $status . '</title>'
            . '<body style="font-family:sans-serif;text-align:center;padding:60px 20px">'
            . '<h1 style="font-size:64px;margin:0;color:#e74c3c">' . $status . '</h1>'
            . '<p>' . e($message) . '</p>' . $detail
            . '<a href="/">返回首页</a></body></html>',
            $status
        );
    }

    public function isDebug(): bool
    {
        return (bool)$this->make(Config::class)->get('app.debug', false);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . $path : '');
    }

    /** 服务提供者实例(调度注册用) */
    public function providers(): array
    {
        return $this->providers;
    }
}
