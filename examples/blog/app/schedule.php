<?php

declare(strict_types=1);

/**
 * 调度注册表(演示多种频率 + command 类型任务)
 * 调试:php bin/console schedule:list / schedule:run
 */

use AlphaKit\Schedule\Scheduler;

/** @var Scheduler $schedule */

// 闭包任务:每 5 分钟预热热门文章缓存
$schedule->call(function (): void {
    cache_store()->getOrSet('hot-posts', 300, function (): array {
        return \App\Models\Post::query()->where('published', '=', 1)->orderBy('views', 'desc')->limit(5)->get();
    });
})->everyFiveMinutes()->name('预热热门文章缓存');

// command 类型任务:每天凌晨 3 点清理 30 天前草稿
$schedule->command('posts:cleanup')->dailyAt('03:00')->name('清理超期草稿');

// 标准 cron:每小时整点生成 sitemap(直接派发队列任务)
$schedule->call(fn () => dispatch(new \App\Jobs\GenerateSitemap()))->hourly()->name('每小时 sitemap');

// 每 15 分钟:标签缓存预热(everyFifteenMinutes 表达式演示)
$schedule->call(fn () => dispatch(new \App\Jobs\WarmTagCache()))->everyFifteenMinutes()->name('标签缓存预热');
