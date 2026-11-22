<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;

/**
 * 清理已完成超过 30 天的任务(定时调度演示)
 */
final class CleanDoneTasks
{
    public int $tries = 1;

    public function handle(): void
    {
        $threshold = date('Y-m-d H:i:s', strtotime('-30 days'));

        $deleted = Task::query()
            ->where('done', '=', 1)
            ->where('updated_at', '<', $threshold)
            ->delete();

        logger('cleanup')->info('已清理完成超期任务', ['deleted' => $deleted]);
    }
}
