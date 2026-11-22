<?php

declare(strict_types=1);

namespace AlphaKit\Queue;

use AlphaKit\Database\Connection;

/**
 * 队列管理器(默认 database 驱动,可选 sync 同步驱动)
 *
 * dispatch(new SendWelcomeEmail($user));           // 异步
 * app(QueueManager::class)->later(60, $job);        // 延迟 60 秒
 */
class QueueManager
{
    public function __construct(protected ?Connection $connection = null, protected ?string $driver = null)
    {
        $this->driver ??= (string)config('queue.default', 'database');
    }

    /** 立即入队 */
    public function push(object $job, string $queue = 'default'): void
    {
        $this->later(0, $job, $queue);
    }

    /** 延迟入队(秒) */
    public function later(int $delay, object $job, string $queue = 'default'): void
    {
        if ($this->driver === 'sync' || $this->driver === 'sync-inline') {
            // 同步驱动:立即执行(测试场景)
            $this->resolveDependencies($job);
            $job->handle();
            return;
        }

        $this->ensureJobsTable();

        $this->connection()->insert('jobs', [
            'queue' => $queue,
            'payload' => serialize($job),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => date('Y-m-d H:i:s', time() + $delay),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 取出一条到期任务(原子预留)
     *
     * @return array{id: int, payload: string, attempts: int}|null
     */
    public function pop(string $queue = 'default'): ?array
    {
        $this->ensureJobsTable();
        $conn = $this->connection();
        $t = $conn->wrap('jobs');
        [$idCol, $queueCol, $payloadCol, $attemptsCol, $reservedCol, $availableCol] = array_map(
            [$conn, 'wrap'],
            ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at']
        );

        // 预留超时任务(超过 60s 未完成的视为失败可重试)
        $conn->execute(
            "UPDATE {$t} SET {$reservedCol} = NULL WHERE {$reservedCol} IS NOT NULL AND {$reservedCol} < ?",
            [date('Y-m-d H:i:s', time() - 60)]
        );

        $job = $conn->selectOne(
            "SELECT {$idCol}, {$payloadCol}, {$attemptsCol} FROM {$t}"
            . " WHERE {$queueCol} = ? AND {$reservedCol} IS NULL AND {$availableCol} <= ?"
            . " ORDER BY {$idCol} ASC LIMIT 1",
            [$queue, date('Y-m-d H:i:s')]
        );

        if ($job === null) {
            return null;
        }

        // 原子抢占:预留成功才返回
        $reserved = $conn->execute(
            "UPDATE {$t} SET {$reservedCol} = ?, {$attemptsCol} = {$attemptsCol} + 1 WHERE {$idCol} = ? AND {$reservedCol} IS NULL",
            [date('Y-m-d H:i:s'), $job['id']]
        );

        return $reserved > 0 ? ['id' => (int)$job['id'], 'payload' => (string)$job['payload'], 'attempts' => (int)$job['attempts'] + 1] : null;
    }

    /** 完成任务:删除 */
    public function deleteJob(int $id): void
    {
        $conn = $this->connection();
        $idCol = $conn->wrap('id');
        $conn->delete('jobs', "{$idCol} = ?", [$id]);
    }

    /** 释放任务回队列(延迟重试) */
    public function release(int $id, int $delay = 0): void
    {
        $conn = $this->connection();
        $t = $conn->wrap('jobs');
        [$idCol, $reservedCol, $availableCol] = array_map([$conn, 'wrap'], ['id', 'reserved_at', 'available_at']);
        $conn->execute(
            "UPDATE {$t} SET {$reservedCol} = NULL, {$availableCol} = ? WHERE {$idCol} = ?",
            [date('Y-m-d H:i:s', time() + $delay), $id]
        );
    }

    /** 记录失败任务 */
    public function recordFailure(array $job, \Throwable $e): void
    {
        $this->ensureFailedTable();
        $conn = $this->connection();
        $conn->insert('failed_jobs', [
            'queue' => 'default',
            'payload' => $job['payload'],
            'error' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** 待处理数量 */
    public function size(string $queue = 'default'): int
    {
        $this->ensureJobsTable();
        $conn = $this->connection();
        $t = $conn->wrap('jobs');
        $queueCol = $conn->wrap('queue');
        return (int)$conn->scalar(
            "SELECT COUNT(*) FROM {$t} WHERE {$queueCol} = ?",
            [$queue]
        );
    }

    protected function connection(): Connection
    {
        return $this->connection ??= app(Connection::class);
    }

    /** 简易容器注入:给 job 的带类型提示公共属性赋值 */
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
            // 忽略反射失败
        }
    }

    protected function ensureJobsTable(): void
    {
        $conn = $this->connection();
        if ($conn->tableExists('jobs')) {
            return;
        }
        $isMysql = $conn->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
        // 列名按方言转义:MySQL 默认模式下双引号是字符串字面量,硬编码会语法错误;
        // MySQL 下短列用 VARCHAR(TEXT 组合索引超 3072 字节上限,报 1071)
        $q = $isMysql ? '`' : '"';
        $short = $isMysql ? 'VARCHAR(100)' : 'TEXT';
        $stamp = $isMysql ? 'VARCHAR(32)' : 'TEXT';
        $id = $isMysql
            ? '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
        $conn->execute("CREATE TABLE IF NOT EXISTS {$conn->wrap('jobs')} (
            {$id},
            {$q}queue{$q} {$short} NOT NULL DEFAULT 'default',
            {$q}payload{$q} TEXT NOT NULL,
            {$q}attempts{$q} INTEGER NOT NULL DEFAULT 0,
            {$q}reserved_at{$q} {$stamp} NULL,
            {$q}available_at{$q} {$stamp} NOT NULL,
            {$q}created_at{$q} {$stamp} NOT NULL
        )");
        if ($isMysql) {
            $conn->execute("CREATE INDEX `jobs_queue_idx` ON {$conn->wrap('jobs')} (`queue`, `reserved_at`, `available_at`)");
        } else {
            $conn->execute('CREATE INDEX IF NOT EXISTS "jobs_queue_idx" ON ' . $conn->wrap('jobs') . ' ("queue", "reserved_at", "available_at")');
        }
    }

    protected function ensureFailedTable(): void
    {
        $conn = $this->connection();
        if ($conn->tableExists('failed_jobs')) {
            return;
        }
        $isMysql = $conn->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
        $q = $isMysql ? '`' : '"';
        $id = $isMysql
            ? '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
        $conn->execute("CREATE TABLE IF NOT EXISTS {$conn->wrap('failed_jobs')} (
            {$id},
            {$q}queue{$q} TEXT NOT NULL DEFAULT 'default',
            {$q}payload{$q} TEXT NOT NULL,
            {$q}error{$q} TEXT NOT NULL,
            {$q}failed_at{$q} TEXT NOT NULL
        )");
    }
}
