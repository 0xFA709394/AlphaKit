<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 单条路由定义
 */
class Route
{
    /** 注册该路由的 Router(由 addRoute 注入,name() 回写用) */
    protected ?Router $router = null;

    /** @param mixed $handler 闭包或 [Controller::class, 'method'] */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler,
        public array $middleware = []
    ) {
    }

    /** 注入所属路由器 */
    public function setRouter(Router $router): self
    {
        $this->router = $router;
        return $this;
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

    /**
     * 把 {id} / {id:\d+} / {id?} 编译为正则
     * 可选参数 {x?} 连同前导斜杠整体可选:/news/{page?} 同时匹配 /news 与 /news/3
     */
    protected function compile(): string
    {
        // 1) 可选参数:先在原始 path 上处理,便于把 "/{x?}" 整段替换为 "(?:/...)?"
        $path = (string)preg_replace_callback(
            '#/\{(\w+)(?::([^}]+?))?\?\}#',
            fn (array $m) => '(?:/(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . '))?',
            $this->path
        );

        // 2) 转义斜杠
        $path = str_replace('/', '\/', $path);

        // 3) 必填参数
        return (string)preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            fn (array $m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $path
        );
    }

    /** 附加中间件 */
    public function middleware(string ...$middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /** 命名路由(注册到所属 Router) */
    public function name(string $name): self
    {
        $owner = $this->router ?? app(Router::class);
        $owner->registerNamed($name, $this);
        return $this;
    }
}
