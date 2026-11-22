<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tag;
use AlphaKit\Database\QueryBuilder;

/**
 * Tag 缓存预热任务(make:job 生成后填充)
 * 派发到命名队列 tags:php bin/console queue:work --queue=tags
 */
final class WarmTagCache
{
    public int $tries = 2;
    public int $retryAfter = 3;

    public function handle(): void
    {
        // 缓存 getOrSet(remember 语义)
        $count = cache_store()->getOrSet('tags-count', 600, fn () => Tag::count());
        $max = (int)(QueryBuilder::table('tags')->max('weight') ?? 0);
        logger('tags')->info('标签缓存预热完成', ['count' => $count, 'max_weight' => $max]);
    }

    public function failed(\Throwable $e): void
    {
        logger('tags')->error('标签缓存预热失败', ['error' => $e->getMessage()]);
    }
}
