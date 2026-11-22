<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostPublished;
use App\Jobs\GenerateSitemap;

/**
 * 监听器二:派发队列任务(事件 → 队列 异步链路)
 */
final class QueueSitemapGeneration
{
    public function handle(PostPublished $event): string
    {
        dispatch(new GenerateSitemap());
        return 'sitemap-queued';
    }
}
