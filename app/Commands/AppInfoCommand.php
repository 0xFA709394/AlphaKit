<?php

declare(strict_types=1);

namespace App\Commands;

use AlphaKit\Console\Command;

/**
 * 应用信息: php bin/alphakit app:info
 */
final class AppInfoCommand extends Command
{
    protected string $signature = 'app:info';
    protected string $description = '显示应用概要信息(演示自定义命令)';

    protected function handle(): ?int
    {
        $this->table(['项目', '值'], [
            ['应用', (string)config('app.name')],
            ['环境', (string)config('app.env')],
            ['框架版本', (string)config('app.version')],
            ['用户数', (string)\App\Models\User::count()],
            ['任务数', (string)\App\Models\Task::count()],
            ['待处理队列', (string)app(\AlphaKit\Queue\QueueManager::class)->size()],
        ]);
        return self::SUCCESS;
    }
}
