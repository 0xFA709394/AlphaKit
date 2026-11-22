<?php

declare(strict_types=1);

namespace App\Commands;

use AlphaKit\Console\Command;
use App\Models\Post;

/**
 * php bin/console posts:cleanup —— 调度器 command 类型任务的执行体
 */
final class PostsCleanupCommand extends Command
{
    protected string $signature = 'posts:cleanup {--days=30 : 清理多少天前的未发布草稿}';
    protected string $description = '清理超期未发布草稿';

    protected function handle(): ?int
    {
        $days = (int)$this->option('days');
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = Post::query()
            ->where('published', '=', 0)
            ->where('created_at', '<', $threshold)
            ->delete();

        $this->info("清理完成:删除 {$deleted} 篇 {$days} 天前的草稿");
        logger('cleanup')->info('草稿清理', ['deleted' => $deleted, 'days' => $days]);
        return self::SUCCESS;
    }
}
