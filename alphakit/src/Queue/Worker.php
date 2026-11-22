<?php

declare(strict_types=1);

namespace AlphaKit\Queue;

use AlphaKit\Log\Logger;

/**
 * 队列工作进程
 *
 * php bin/alphakit queue:work              # 常驻
 * php bin/alphakit queue:work --once       # 处理一条退出(配合 cron)
 * php bin/alphakit queue:work --queue=high # 指定队列
 *
 * 任务约定:
 * - 实现 handle() 方法执行业务
 * - 可选 public int $tries = 3          最大尝试次数
 * - 可选 public int $retryAfter = 10    重试延迟(秒)
 * - 可选 public function failed(Throwable $e): void  最终失败回调
 */
class Worker
{
    protected int $processed = 0;
    protected int $failed = 0;

    public function __construct(protected QueueManager $queue, protected Logger $logger)
    {
    }

    /**
     * @param string $queue    队列名
     * @param bool   $daemon   常驻模式
     * @param int    $maxJobs  最大处理条数(>0 时为"批量清空"模式:处理到 N 条或队列为空即退出,适合配合 cron)
     */
    public function run(string $queue = 'default', bool $daemon = true, int $maxJobs = 0, int $sleep = 1): int
    {
        $idleCount = 0;
        while (true) {
            $job = $this->queue->pop($queue);

            if ($job === null) {
                // 非常驻 或 批量模式:队列已空即退出
                if (!$daemon || $maxJobs > 0) {
                    break;
                }
                sleep(max(0, $sleep));
                // 支持优雅退出
                if (function_exists('pcntl_signal')) {
                    pcntl_signal_dispatch();
                }
                continue;
            }

            $this->process($job);

            if ($maxJobs > 0 && $this->processed >= $maxJobs) {
                break;
            }
        }

        return $this->failed;
    }

    /** 处理单个任务 */
    public function process(array $job): void
    {
        $instance = @unserialize($job['payload']);

        // 坏任务判定:反序列化失败 / 非对象 / 类定义缺失(incomplete) / 无 handle()
        // 类缺失常见于部署了旧 worker 或 job 类改名,必须删除并记录而不是让进程崩溃
        if ($instance === false
            || !is_object($instance)
            || $instance instanceof \__PHP_Incomplete_Class
            || !method_exists($instance, 'handle')
        ) {
            $this->queue->deleteJob($job['id']);
            $this->failed++;
            $this->logger->error('队列任务无法反序列化(类缺失或结构非法)', ['id' => $job['id'], 'attempts' => $job['attempts']]);
            return;
        }

        try {
            $tries = (int)(isset($instance->tries) ? $instance->tries : 1);

            $this->resolveDependencies($instance);
            $instance->handle();
            $this->queue->deleteJob($job['id']);
            $this->processed++;
            $this->logger->info('队列任务完成', ['job' => $instance::class, 'id' => $job['id']]);
        } catch (\Throwable $e) {
            $this->logger->error('队列任务失败', [
                'job' => $instance::class,
                'id' => $job['id'],
                'attempts' => $job['attempts'],
                'error' => $e->getMessage(),
            ]);

            if ($job['attempts'] < $tries) {
                $retryAfter = (int)(isset($instance->retryAfter) ? $instance->retryAfter : 5);
                $this->queue->release($job['id'], $retryAfter);
            } else {
                $this->queue->deleteJob($job['id']);
                $this->queue->recordFailure($job, $e);
                $this->failed++;
                if (method_exists($instance, 'failed')) {
                    try {
                        $instance->failed($e);
                    } catch (\Throwable) {
                        // 失败回调异常不再传播
                    }
                }
            }
        }
    }

    /** 给 job 注入容器依赖 */
    protected function resolveDependencies(object $job): void
    {
        try {
            $ref = new \ReflectionClass($job);
            foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && $property->getValue($job) === null) {
                    $property->setValue($job, app($type->getName()));
                }
            }
        } catch (\ReflectionException) {
        }
    }

    public function stats(): array
    {
        return ['processed' => $this->processed, 'failed' => $this->failed];
    }
}
