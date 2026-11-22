<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Database\Connection;

/**
 * 查看失败任务: php bin/alphakit queue:failed
 */
class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed';
    protected string $description = '列出全部失败的队列任务';

    protected function handle(): ?int
    {
        $conn = app(Connection::class);
        [$idCol, $queueCol, $errorCol, $failedAtCol] = array_map([$conn, 'wrap'], ['id', 'queue', 'error', 'failed_at']);
        $table = $conn->wrap('failed_jobs');

        $rows = [];
        foreach ($conn->select("SELECT {$idCol}, {$queueCol}, {$errorCol}, {$failedAtCol} FROM {$table} ORDER BY {$idCol} DESC LIMIT 50") as $row) {
            $rows[] = [$row['id'], $row['queue'], mb_substr((string)$row['error'], 0, 60), $row['failed_at']];
        }

        if ($rows === []) {
            $this->info('没有失败任务');
            return self::SUCCESS;
        }
        $this->table(['ID', 'Queue', 'Error', 'Failed At'], $rows);
        return self::SUCCESS;
    }
}
