<?php

declare(strict_types=1);

namespace AlphaKit\Cache;

/**
 * 内存缓存驱动(单请求生命周期,适合测试)
 */
class ArrayStore extends Store
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    protected array $storage = [];

    public function get(string $key)
    {
        $item = $this->storage[$key] ?? null;
        if ($item === null) {
            return null;
        }
        if ($item['expires_at'] !== 0 && $item['expires_at'] < time()) {
            unset($this->storage[$key]);
            return null;
        }
        return $item['value'];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->storage[$key] = ['value' => $value, 'expires_at' => $ttl === null ? 0 : time() + $ttl];
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }
}
