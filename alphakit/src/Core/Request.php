<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * HTTP 请求抽象
 * 封装 method / uri / query / body(json 与表单) / headers / files / ip
 */
class Request
{
    public readonly string $method;
    /** @var array<string,mixed> 查询参数 */
    public readonly array $query;
    /** @var array<string,mixed> 请求体 */
    public readonly array $body;
    /** @var array<string,mixed> 路由参数 */
    public array $params = [];

    /** @var array<string,mixed>|null 当前认证用户(auth 中间件注入) */
    public ?array $user = null;
    /** @var array<string,mixed> 服务端变量 */
    protected readonly array $server;

    protected static bool $trustProxy = false;

    public function __construct(
        ?string $method = null,
        ?string $uri = null,
        array $query = [],
        array $body = [],
        array $server = []
    ) {
        $this->server = $server ?: $_SERVER;
        $this->method = strtoupper($method ?? ($this->server['REQUEST_METHOD'] ?? 'GET'));
        $this->query = $query ?: $_GET;
        $this->body = !empty($body) ? $body : $this->parseBody();
    }

    /** 解析请求体:JSON 优先,其次表单 */
    protected function parseBody(): array
    {
        $contentType = $this->header('content-type') ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        if ($this->method === 'POST' || str_contains($contentType, 'form-urlencoded') || str_contains($contentType, 'multipart')) {
            return $_POST;
        }
        // PUT/PATCH/DELETE 表单
        if (in_array($this->method, ['PUT', 'PATCH', 'DELETE'], true)) {
            $raw = file_get_contents('php://input') ?: '';
            parse_str($raw, $parsed);
            return $parsed;
        }
        return [];
    }

    /** 原始请求体 */
    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /** 请求路径(不含 query string) */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path === null ? '/' : $path;
    }

    /** 完整 URL */
    public function url(): string
    {
        $scheme = ($this->server['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $host = $this->header('host') ?? 'localhost';
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    /** 获取请求头(不区分大小写) */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return $this->server[$key];
        }
        if (isset($this->server[strtoupper(str_replace('-', '_', $name))])) {
            return $this->server[strtoupper(str_replace('-', '_', $name))];
        }
        return null;
    }

    /** 所有请求头 */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /** 客户端 IP */
    public function ip(): string
    {
        if (self::$trustProxy) {
            $forwarded = $this->header('x-forwarded-for');
            if ($forwarded) {
                return trim(explode(',', $forwarded)[0]);
            }
        }
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 获取输入值:body 优先,其次 query
     *
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** 合并 body + query */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** 仅获取指定键 */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    /** 是否为 AJAX */
    public function ajax(): bool
    {
        return str_contains($this->header('x-requested-with') ?? '', 'XMLHttpRequest');
    }

    /** 是否期望 JSON 响应 */
    public function expectsJson(): bool
    {
        return str_contains($this->header('accept') ?? '', 'json')
            || str_contains($this->path(), '/api');
    }

    /** Bearer Token */
    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization') ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        return null;
    }

    /** 静默捕获验证辅助:从工厂创建(供测试使用) */
    public static function capture(): self
    {
        return new self();
    }
}
