<?php

declare(strict_types=1);

namespace AlphaKit\Http;

/**
 * HTTP 客户端响应
 */
class HttpResponse
{
    public function __construct(
        public readonly int $status,
        protected string $body,
        protected array $headers = []
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return mixed */
    public function json()
    {
        return json_decode($this->body, true);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function headers(): array
    {
        return $this->headers;
    }
}
