<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SlugifierInterface;
use App\Events\PostPublished;
use App\Listeners\{QueueSitemapGeneration, WriteAuditLog};
use App\Services\SimpleSlugifier;
use AlphaKit\Core\ServiceProvider;

/**
 * 服务提供者演示:
 * register —— 容器绑定(接口 → 实现,构造注入时自动解析)
 * boot     —— 事件订阅(观察者注册)
 */
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SlugifierInterface::class, SimpleSlugifier::class);
    }

    public function boot(): void
    {
        app(\AlphaKit\Core\Event\EventDispatcher::class)->on(PostPublished::class, [
            [WriteAuditLog::class, 'handle'],
            [QueueSitemapGeneration::class, 'handle'],
        ]);
    }
}
