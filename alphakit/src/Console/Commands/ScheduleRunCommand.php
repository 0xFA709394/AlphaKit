<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Schedule\Scheduler;

/**
 * 运行到期调度任务: php bin/alphakit schedule:run (系统 cron 每分钟调用)
 */
class ScheduleRunCommand extends Command
{
    protected string $signature = 'schedule:run';
    protected string $description = '运行当前分钟到期的调度任务';

    protected function handle(): ?int
    {
        /** @var Scheduler $scheduler */
        $scheduler = app(Scheduler::class);
        $scheduler->loadApplicationSchedule();

        $due = $scheduler->dueTasks(time());

        if ($due === []) {
            $this->line('没有到期任务 (' . date('Y-m-d H:i') . ')');
            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($due as $task) {
            $ok = $task->execute();
            $this->{$ok ? 'info' : 'error'}(($ok ? '[OK] ' : '[FAIL] ') . $task->label());
            if (!$ok) {
                $failed++;
            }
        }
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
