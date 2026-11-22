<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Schedule\Scheduler;

/**
 * 调度任务列表: php bin/alphakit schedule:list
 */
class ScheduleListCommand extends Command
{
    protected string $signature = 'schedule:list';
    protected string $description = '列出全部注册的调度任务';

    protected function handle(): ?int
    {
        /** @var Scheduler $scheduler */
        $scheduler = app(Scheduler::class);
        $scheduler->loadApplicationSchedule();

        $tasks = $scheduler->all();
        if ($tasks === []) {
            $this->info('没有注册调度任务 (可在 app/schedule.php 中注册)');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [$task->expression(), $task->label(), $task->isDue(time()) ? '是' : '否'];
        }
        $this->table(['Cron', 'Task', '当前分钟到期'], $rows);
        return self::SUCCESS;
    }
}
