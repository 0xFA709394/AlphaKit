<?php

declare(strict_types=1);

namespace AlphaKit\Cache;

/**
 * Redis 缓存驱动(依赖 ext-redis)
 */
class RedisStore extends Store
{
    protected ?\Redis $client = null;

    public function __construct(protected array $config = [])
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis 驱动需要 ext-redis 扩展');
        }
    }

    protected function client(): \Redis
    {
        if ($this->client === null) {
            $config = $this->config ?: (array)config('cache.stores.redis', []);
            $this->client = new \Redis();
            $this->client->connect(
                $config['host'] ?? '127.0.0.1',
                (int)($config['port'] ?? 6379)
            );
            if (!empty($config['password'])) {
                $this->client->auth($config['password']);
            }
            if (!empty($config['database'])) {
                $this->client->select((int)$config['database']);
            }
        }
        return $this->client;
    }

    protected function k(string $key): string
    {
        $prefix = $this->config['prefix'] ?? (config('cache.stores.redis.prefix') ?? 'alphakit:');
        return $prefix . $key;
    }

    public function get(string $key)
    {
        $raw = $this->client()->get($this->k($key));
        return $raw === false ? null : unserialize((string)$raw);
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        return $this->client()->set($this->k($key), serialize($value), $ttl ?? 0);
    }

    public function delete(string $key): bool
    {
        return (bool)$this->client()->del($this->k($key));
    }

    public function clear(): bool
    {
        $keys = $this->client()->keys($this->k('*'));
        if ($keys) {
            $this->client()->del($keys);
        }
        return true;
    }
}
