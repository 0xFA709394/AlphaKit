<?php

declare(strict_types=1);

namespace App\Commands;

use AlphaKit\Console\Command;
use App\Models\Task;

/**
 * 任务统计: php bin/alphakit task:stats
 */
final class TaskStatsCommand extends Command
{
    protected string $signature = 'task:stats {--user= : 仅统计指定用户 ID}';
    protected string $description = '输出任务完成情况统计';

    protected function handle(): ?int
    {
        $query = Task::query();
        if (($userId = $this->option('user')) !== null && $userId !== true) {
            $query->where('user_id', '=', (int)$userId);
        }

        $total = $query->count();
        $done = (clone $query)->where('done', '=', 1)->count();

        $this->table(['指标', '值'], [
            ['总数', (string)$total],
            ['已完成', (string)$done],
            ['进行中', (string)($total - $done)],
            ['完成率', $total > 0 ? round($done / $total * 100, 1) . '%' : '-'],
        ]);
        return self::SUCCESS;
    }
}
