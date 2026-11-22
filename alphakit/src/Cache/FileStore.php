<?php

declare(strict_types=1);

namespace AlphaKit\Cache;

/**
 * 文件缓存驱动
 * 序列化存储,支持 TTL;文件名做 SHA1 处理避免特殊字符问题
 */
class FileStore extends Store
{
    protected string $directory;

    public function __construct(?array $config = null)
    {
        $path = $config['path'] ?? config('cache.stores.file.path', storage_path('cache/data'));
        $this->directory = rtrim((string)$path, '/');
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key)
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $payload = @unserialize($raw);
        if (!is_array($payload) || !isset($payload['expires_at'], $payload['value'])) {
            return null;
        }
        // 过期删除
        if ($payload['expires_at'] !== 0 && $payload['expires_at'] < time()) {
            @unlink($file);
            return null;
        }
        return $payload['value'];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $payload = serialize([
            'value' => $value,
            'expires_at' => $ttl === null ? 0 : time() + $ttl,
        ]);
        return file_put_contents($this->path($key), $payload, LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->path($key);
        return !is_file($file) || @unlink($file);
    }

    public function clear(): bool
    {
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
        return true;
    }

    protected function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.cache';
    }
}
