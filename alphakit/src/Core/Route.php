<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 单条路由定义
 */
class Route
{
    /** @param mixed $handler 闭包或 [Controller::class, 'method'] */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler,
        public array $middleware = []
    ) {
    }

    /**
     * 判断路径是否匹配
     *
     * @return array<string, string>|null 命中返回参数字典,未命中返回 null
     */
    public function matches(string $path): ?array
    {
        $pattern = '#^' . $this->compile() . '$#';
        if (!preg_match($pattern, rtrim($path, '/') ?: '/', $matches)) {
            return null;
        }
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** 把 {id} / {id:\d+} / {id?} 编译为正则 */
    protected function compile(): string
    {
        return (string)preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\??\}#',
            function (array $m) {
                $pattern = $m[2] ?? '[^/]+';
                return substr($m[0], -2) === '?}'
                    ? '(?P<' . $m[1] . '>' . $pattern . ')?'
                    : '(?P<' . $m[1] . '>' . $pattern . ')';
            },
            str_replace('/', '\/', $this->path)
        );
    }

    /** 附加中间件 */
    public function middleware(string ...$middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /** 命名路由 */
    public function name(string $name): self
    {
        app(Router::class)->registerNamed($name, $this);
        return $this;
    }
}
