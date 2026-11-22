<?php

declare(strict_types=1);

namespace AlphaKit\Cache;

/**
 * 缓存存储抽象:各驱动实现 get/set/delete/clear/has,
 * remember/pull/increment 等组合操作由基类提供
 */
abstract class Store implements StoreInterface
{
    abstract public function get(string $key);

    abstract public function set(string $key, $value, ?int $ttl = null): bool;

    abstract public function delete(string $key): bool;

    abstract public function clear(): bool;

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function getOrSet(string $key, ?int $ttl, callable $callback)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function pull(string $key, $default = null)
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        $this->delete($key);
        return $value;
    }

    public function increment(string $key, int $amount = 1): int
    {
        $value = (int)$this->get($key) + $amount;
        $this->set($key, $value);
        return $value;
    }

    /** @param mixed $default */
    public function getWithDefault(string $key, $default = null)
    {
        $value = $this->get($key);
        return $value === null ? $default : $value;
    }
}
