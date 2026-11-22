<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * HTTP 响应:流式 API 构造,支持 json / html / text / 重定向 / 文件下载
 */
class Response
{
    protected string $body = '';
    protected int $status = 200;
    /** @var array<string, string> */
    protected array $headers = [];

    // 常用状态码文案
    public const STATUS_TEXTS = [
        200 => 'OK', 201 => 'Created', 204 => 'No Content',
        301 => 'Moved Permanently', 302 => 'Found', 304 => 'Not Modified',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
        404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict',
        422 => 'Unprocessable Entity', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
    ];

    public function __construct($body = '', int $status = 200, array $headers = [])
    {
        $this->body = is_array($body) ? http_build_query($body) : (string)$body;
        $this->status = $status;
        foreach ($headers as $name => $value) {
            $this->headers[$name] = (string)$value;
        }
    }

    public static function json($data, int $status = 200, array $headers = []): self
    {
        // INVALID_UTF8_SUBSTITUTE:非法 UTF-8 序列替换为 �,避免 json_encode 失败返回空体
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $resp = new self($body === false ? '{}' : $body, $status, $headers);
        return $resp->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        $resp = new self($content, $status, $headers);
        return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public static function text(string $content, int $status = 200, array $headers = []): self
    {
        $resp = new self($content, $status, $headers);
        return $resp->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $url);
    }

    /** 统一成功 JSON 结构 */
    public static function ok($data = null, string $message = 'ok'): self
    {
        return self::json(['code' => 0, 'message' => $message, 'data' => $data]);
    }

    /** 统一失败 JSON 结构 */
    public static function fail(string $message = 'error', int $code = 1, int $status = 400, $errors = null): self
    {
        $payload = ['code' => $code, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        return self::json($payload, $status);
    }

    /** 下载文件 */
    public static function download(string $filePath, ?string $name = null): self
    {
        $name = $name ?? basename($filePath);
        $resp = new self(file_get_contents($filePath) ?: '');
        return $resp->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @var array<string, array{string, string, int}> cookies 待发送 */
    protected array $cookieQueue = [];

    public function withCookie(string $name, string $value, int $expire = 0): self
    {
        $this->cookieQueue[] = [$name, $value, $expire];
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /** 发送响应到 STDOUT(php 内置服务器 / fpm) */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            foreach ($this->cookieQueue as [$name, $value, $expire]) {
                setcookie($name, $value, $expire);
            }
        }
        echo $this->body;
    }
}
