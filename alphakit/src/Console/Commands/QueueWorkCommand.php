<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Queue\Worker;

/**
 * 队列消费: php bin/alphakit queue:work [--queue=default] [--once] [--max-jobs=100]
 */
class QueueWorkCommand extends Command
{
    protected string $signature = 'queue:work {--queue=default : 队列名} {--once : 处理一条后退出} {--max-jobs= : 最大处理条数}';
    protected string $description = '启动队列工作进程(消费异步任务)';

    protected function handle(): ?int
    {
        $queue = (string)$this->option('queue', 'default');
        $daemon = !$this->option('once');
        $maxJobs = (int)($this->option('max-jobs') ?? 0);

        $this->info("队列 worker 已启动 (queue={$queue}, daemon=" . ($daemon ? 'yes' : 'no') . ')');

        $worker = app(Worker::class);
        $failed = $worker->run($queue, $daemon, $maxJobs);

        $stats = $worker->stats();
        if (!$daemon) {
            $this->line("处理 {$stats['processed']} 条, 失败 {$stats['failed']} 条");
        }
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
