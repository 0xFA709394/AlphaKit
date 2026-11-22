<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 文件 Session(独立实现,不依赖 php.ini session 配置)
 *
 * session()->put('user_id', 1);
 * $id = session()->get('user_id');
 * session()->flash('message', '创建成功');   // 下次请求读取后自动清除
 */
class Session
{
    protected string $id;
    protected array $data = [];
    protected bool $started = false;

    /** flash 闪存数据的 meta 键 */
    protected const FLASH_KEY = '__flash__';

    public function __construct(protected string $directory = '')
    {
        $this->directory = rtrim($directory ?: storage_path('cache/sessions'), '/');
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /** 启动会话(从 cookie 恢复) */
    public function start(Request $request): self
    {
        if ($this->started) {
            return $this;
        }
        $this->started = true;

        $cookieName = (string)config('session.cookie', 'alphakit_session');
        $this->id = $_COOKIE[$cookieName] ?? '';

        if (!preg_match('/^[a-f0-9]{32,64}$/', (string)$this->id)) {
            $this->id = bin2hex(random_bytes(16));
        }

        $file = $this->file();
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $this->data = $raw === false ? [] : (array)@unserialize($raw);
        }

        // 上一次请求设置的 flash 标记 -> 本次消费后清除
        if (isset($this->data[self::FLASH_KEY]['new'])) {
            $this->data[self::FLASH_KEY]['old'] = $this->data[self::FLASH_KEY]['new'];
            $this->data[self::FLASH_KEY]['new'] = [];
        }

        return $this;
    }

    /** @return mixed */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    /** 全部清空 */
    public function flush(): void
    {
        $this->data = [];
    }

    /** 闪存:写入后只在下一次请求可见 */
    public function flash(string $key, $value): void
    {
        $this->data[self::FLASH_KEY]['new'][$key] = $value;
    }

    /** @return mixed */
    public function getFlash(string $key, $default = null)
    {
        return $this->data[self::FLASH_KEY]['old'][$key] ?? $default;
    }

    /** 重建会话 ID(防会话固定) */
    public function regenerate(): void
    {
        @unlink($this->file());
        $this->id = bin2hex(random_bytes(16));
    }

    /** 持久化并发送 cookie */
    public function save(Response $response): Response
    {
        if (!$this->started) {
            return $response;
        }

        file_put_contents($this->file(), serialize($this->data), LOCK_EX);

        $cookieName = (string)config('session.cookie', 'alphakit_session');
        $lifetime = (int)config('session.lifetime', 7200);
        return $response->withCookie($cookieName, $this->id, time() + $lifetime);
    }

    public function id(): string
    {
        return $this->id ?? '';
    }

    protected function file(): string
    {
        return $this->directory . '/sess_' . sha1($this->id);
    }
}
