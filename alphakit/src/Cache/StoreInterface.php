<?php

declare(strict_types=1);

namespace AlphaKit\Cache;

interface StoreInterface
{
    /** @return mixed */
    public function get(string $key);

    public function set(string $key, $value, ?int $ttl = null): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function has(string $key): bool;
}
