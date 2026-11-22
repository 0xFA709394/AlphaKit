<?php

declare(strict_types=1);

namespace AlphaKit\Cache;

/**
 * 缓存管理器:按名称解析驱动(file / redis / array)
 *
 *   cache_store()->set('key', $value, 3600);
 *   cache_store()->getOrSet('hot_data', 300, fn() => expensiveQuery());
 *   cache_store('redis')->increment('counter');
 */
class CacheManager
{
    /** @var array<string, Store> 驱动实例池 */
    protected array $stores = [];

    public function __construct(protected array $config = [])
    {
    }

    /** 获取缓存存储实例 */
    public function store(?string $name = null): Store
    {
        $name = $name ?? (string)($this->config['default'] ?? config('cache.default', 'file'));

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        $storeConfig = (array)($this->config['stores'][$name] ?? config('cache.stores.' . $name, []));
        $driver = $storeConfig['driver'] ?? $name;

        $store = match ($driver) {
            'redis' => extension_loaded('redis') ? new RedisStore($storeConfig) : new FileStore(),
            'array' => new ArrayStore(),
            default => new FileStore($storeConfig),
        };

        return $this->stores[$name] = $store;
    }
}
