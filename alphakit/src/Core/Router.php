<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 路由器
 *
 * 特性:
 * - RESTful 谓词注册: get/post/put/patch/delete/any
 * - 路由参数 {id},支持正则约束 {id:\d+}
 * - 可选参数 {id?}
 * - 分组: prefix / middleware 嵌套
 * - 命名路由: ->name('users.show') 与 url() 生成
 * - 自动区分 404(无路由) / 405(方法不支持)
 */
class Router
{
    /** @var array<string, array<Route>> 按 METHOD 存储的路由 */
    protected array $routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'PATCH' => [], 'DELETE' => [], 'HEAD' => [], 'OPTIONS' => []];

    /** @var array<string, Route> 命名路由 */
    protected array $namedRoutes = [];

    /** @var array{prefix: string[], middleware: array[]} 当前分组栈 */
    protected array $groupStack = ['prefix' => [], 'middleware' => []];

    /** @var Route[] 扁平列表(用于 route:list) */
    protected array $allRoutes = [];

    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function any(string $path, $handler): Route
    {
        $route = null;
        foreach (array_keys($this->routes) as $method) {
            $route = $this->addRoute($method, $path, $handler);
        }
        return $route;
    }

    /**
     * RESTful 资源路由(一次性生成 index/store/show/update/destroy)
     *
     * @param string $basePath 如 "/api/tasks"
     */
    public function resource(string $basePath, string $controller, array $only = []): void
    {
        $map = [
            ['GET', $basePath, 'index'],
            ['POST', $basePath, 'store'],
            ['GET', $basePath . '/{id}', 'show'],
            ['PUT', $basePath . '/{id}', 'update'],
            ['PATCH', $basePath . '/{id}', 'update'],
            ['DELETE', $basePath . '/{id}', 'destroy'],
        ];
        foreach ($map as [$method, $path, $action]) {
            if ($only === [] || in_array($action, $only, true)) {
                $this->addRoute($method, $path, [$controller, $action]);
            }
        }
    }

    /** 分组路由 */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack['prefix'][] = $attributes['prefix'] ?? '';
        $this->groupStack['middleware'][] = $attributes['middleware'] ?? [];

        $callback($this);

        array_pop($this->groupStack['prefix']);
        array_pop($this->groupStack['middleware']);
    }

    protected function addRoute(string $method, string $path, $handler): Route
    {
        $prefix = implode('/', array_filter($this->groupStack['prefix'], fn ($p) => $p !== ''));
        $fullPath = '/' . trim(($prefix ? $prefix . '/' : '') . trim($path, '/'), '/');
        $fullPath = $fullPath === '' ? '/' : $fullPath;

        $middleware = array_merge(...array_merge([[]], $this->groupStack['middleware']));

        $route = new Route($method, $fullPath, $handler, $middleware);
        $route->setRouter($this);
        $this->routes[$method][] = $route;
        $this->allRoutes[] = $route;
        return $route;
    }

    /**
     * 匹配请求
     *
     * @return array{handler: mixed, params: array<string,string>, middleware: array<string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        // HEAD 回落到 GET
        $searchMethods = $method === 'HEAD' ? ['HEAD', 'GET'] : [$method];

        foreach ($searchMethods as $m) {
            foreach ($this->routes[$m] ?? [] as $route) {
                $params = $route->matches($path);
                if ($params !== null) {
                    return ['handler' => $route->handler, 'params' => $params, 'middleware' => $route->middleware];
                }
            }
        }

        // 检查其他方法是否有匹配路径 -> 405
        if ($method !== 'OPTIONS') {
            foreach ($this->routes as $m => $routes) {
                if (in_array($m, $searchMethods, true)) {
                    continue;
                }
                foreach ($routes as $route) {
                    if ($route->matches($path) !== null) {
                        throw new Exception\MethodNotAllowedException($path, $method);
                    }
                }
            }
        }

        return null;
    }

    /** 根据名称生成 URL */
    public function url(string $name, array $params = []): string
    {
        $route = $this->namedRoutes[$name] ?? null;
        if ($route === null) {
            throw new \InvalidArgumentException("路由 [$name] 未定义");
        }
        $path = $route->path;
        foreach ($params as $key => $value) {
            $path = preg_replace('#\{' . $key . '(:[^}]+)?\??\}#', (string)$value, $path, 1);
        }
        // 未提供值的可选参数整段移除(连同前导斜杠)
        $path = (string)preg_replace('#/\{[^}]+\?\}#', '', $path);
        return $path;
    }

    public function registerNamed(string $name, Route $route): void
    {
        $this->namedRoutes[$name] = $route;
    }

    /** 所有路由(用于 route:list) */
    public function all(): array
    {
        return $this->allRoutes;
    }
}
