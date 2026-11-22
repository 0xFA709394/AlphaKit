<?php

declare(strict_types=1);

namespace AlphaKit\Http;

/**
 * cURL HTTP 客户端
 *
 * 简单请求:
 *   HttpClient::client()->get('https://api.example.com/users', ['page' => 1]);
 *   HttpClient::client()->post('https://api.example.com/users', ['name' => 'ak']);
 *
 * 定制请求(链式):
 *   HttpClient::client()->asJson()->withToken($token)->timeout(10)->post($url, $data);
 */
class HttpClient
{
    protected array $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ];

    protected array $headers = [];
    protected ?string $bodyFormat = null;

    /** 创建客户端实例(入口) */
    public static function client(): self
    {
        return new self();
    }

    public function get(string $url, array $query = []): HttpResponse
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->send('GET', $url);
    }

    public function post(string $url, $data = []): HttpResponse
    {
        return $this->send('POST', $url, $data);
    }

    public function put(string $url, $data = []): HttpResponse
    {
        return $this->send('PUT', $url, $data);
    }

    public function patch(string $url, $data = []): HttpResponse
    {
        return $this->send('PATCH', $url, $data);
    }

    public function delete(string $url, $data = []): HttpResponse
    {
        return $this->send('DELETE', $url, $data);
    }

    public function asJson(): self
    {
        $this->bodyFormat = 'json';
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    public function asForm(): self
    {
        $this->bodyFormat = 'form';
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        $this->headers['Authorization'] = $type . ' ' . $token;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->options[CURLOPT_TIMEOUT] = $seconds;
        return $this;
    }

    public function connectTimeout(int $seconds): self
    {
        $this->options[CURLOPT_CONNECTTIMEOUT] = $seconds;
        return $this;
    }

    protected function send(string $method, string $url, $data = []): HttpResponse
    {
        $ch = curl_init($url);
        $options = $this->options;

        $headerLines = [];
        foreach ($this->headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }
        $options[CURLOPT_HTTPHEADER] = $headerLines;

        if ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            if ($data !== [] && $data !== null) {
                if ($this->bodyFormat === 'json' || (is_array($data) && !isset($this->headers['Content-Type']) && $this->bodyFormat === null)) {
                    $this->bodyFormat ??= 'json';
                }
                $options[CURLOPT_POSTFIELDS] = $this->bodyFormat === 'json'
                    ? json_encode($data, JSON_UNESCAPED_UNICODE)
                    : http_build_query((array)$data);
            }
        }

        // 捕获响应头
        $responseHeaders = [];
        $options[CURLOPT_HEADERFUNCTION] = function ($ch, $line) use (&$responseHeaders) {
            $len = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        };

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP 请求失败: ' . $error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new HttpResponse($status, (string)$body, $responseHeaders);
    }
}
