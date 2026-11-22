<?php

declare(strict_types=1);

/**
 * 定时任务注册表
 *
 * 调试:
 *   php bin/alphakit schedule:list     查看全部任务与 cron 表达式
 *   php bin/alphakit schedule:run      执行当前分钟到期的任务
 *
 * 生产(系统 crontab,每分钟):
 *   * * * * * cd /path/to/AlphaKit && php bin/alphakit schedule:run >> /dev/null 2>&1
 */

use App\Jobs\CleanDoneTasks;
use AlphaKit\Schedule\Scheduler;

/** @var Scheduler $schedule */

// 每 5 分钟:清理完成超期任务(派发到队列,由 worker 执行)
$schedule->call(fn () => dispatch(new CleanDoneTasks()))
    ->everyFiveMinutes()
    ->name('清理超期任务');

// 每天凌晨 2:30:备份日志前的清理示例
$schedule->call(function (): void {
    logger('schedule')->info('每日维护任务执行', ['at' => now()]);
    // 例如:归档日志 / 清理过期缓存 / 生成日报
})->dailyAt('02:30')->name('每日维护');

// 每小时:清理过期待处理任务(直接闭包,演示 command 类型可改为)
$schedule->call(function (): void {
    cache_store()->clear(); // 演示用途:实际请按需清理
})->hourly()->name('缓存清理');
