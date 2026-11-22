<?php

declare(strict_types=1);

namespace AlphaKit\Database;

/**
 * 迁移执行器
 * - 扫描 database/migrations 下文件(按文件名排序执行)
 * - migrations 表记录已执行批次
 * - migrate / rollback / status
 */
class Migrator
{
    public function __construct(protected Connection $connection)
    {
        $this->ensureMigrationsTable();
    }

    protected function ensureMigrationsTable(): void
    {
        if (!$this->connection->tableExists('migrations')) {
            $driver = $this->connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $id = $driver === 'mysql' ? '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
            $migration = $this->connection->wrap('migration');
            $batch = $this->connection->wrap('batch');
            $this->connection->execute("CREATE TABLE {$this->connection->wrap('migrations')} ({$id}, {$migration} TEXT NOT NULL, {$batch} INT NOT NULL)");
        }
    }

    /** @return array<string> 迁移文件绝对路径 */
    public function migrationFiles(): array
    {
        $dir = config('database.migrations_path', database_path('migrations'));
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    /** 已执行的迁移名 */
    public function ran(): array
    {
        $t = $this->connection->wrap('migrations');
        $migration = $this->connection->wrap('migration');
        $batch = $this->connection->wrap('batch');
        return array_column($this->connection->select("SELECT {$migration} FROM {$t} ORDER BY {$batch}, {$migration}"), 'migration');
    }

    /** 执行未运行的迁移,返回执行列表 */
    public function migrate(): array
    {
        $ran = $this->ran();
        $t = $this->connection->wrap('migrations');
        $batch = $this->connection->wrap('batch');
        $nextBatch = (int)$this->connection->scalar("SELECT COALESCE(MAX({$batch}), 0) FROM {$t}") + 1;

        $executed = [];
        foreach ($this->migrationFiles() as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = $this->resolve($file, $name);
            $migration->up();
            $this->connection->insert('migrations', ['migration' => $name, 'batch' => $nextBatch]);
            $executed[] = $name;
        }
        return $executed;
    }

    /** 回滚最后一批,返回回滚列表 */
    public function rollback(): array
    {
        $t = $this->connection->wrap('migrations');
        $batch = $this->connection->wrap('batch');
        $migration = $this->connection->wrap('migration');
        $lastBatch = (int)$this->connection->scalar("SELECT MAX({$batch}) FROM {$t}");
        if ($lastBatch < 1) {
            return [];
        }

        $names = array_column(
            $this->connection->select("SELECT {$migration} FROM {$t} WHERE {$batch} = ? ORDER BY {$migration} DESC", [$lastBatch]),
            'migration'
        );

        $rolled = [];
        foreach (array_reverse($names) as $name) {
            $file = $this->findFile($name);
            if ($file === null) {
                continue;
            }
            $this->resolve($file, $name)->down();
            $this->connection->delete('migrations', $this->connection->wrap('migration') . ' = ?', [$name]);
            $rolled[] = $name;
        }
        return $rolled;
    }

    /** 重置全部 */
    public function reset(): array
    {
        $all = [];
        while (true) {
            $batch = $this->rollback();
            if ($batch === []) {
                break;
            }
            $all = array_merge($all, $batch);
        }
        return $all;
    }

    /** 状态表 [name => ran?] */
    public function status(): array
    {
        $ran = $this->ran();
        $status = [];
        foreach ($this->migrationFiles() as $file) {
            $name = basename($file, '.php');
            $status[$name] = in_array($name, $ran, true);
        }
        return $status;
    }

    /**
     * 从迁移文件解析 Migration 实例:
     * 1. 推导类名并预检冲突(类重复声明 fatal 不可捕获,必须 require 前拦截)
     * 2. require 文件:return 实例则直接使用,否则实例化推导类
     * 3. 归属校验:推导类必须由当前文件定义,防止执行错误迁移
     */
    protected function resolve(string $file, string $name): Migration
    {
        // 去掉日期前缀 2024_01_01_000001_
        $base = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name) ?? $name;
        $class = str_replace('_', '', ucwords($base, '_'));

        // require 前预检:类名已存在 = 与其他迁移文件同名(fatal 不可捕获,必须提前拦截)
        if (class_exists($class, false)) {
            $definedIn = (new \ReflectionClass($class))->getFileName();
            throw new \RuntimeException(
                "迁移类名冲突: [$class] 已由 [" . basename((string)$definedIn) . "] 定义,请重命名 [$name] 中的迁移类"
            );
        }

        $result = require $file;

        // 风格一:文件直接 return Migration 实例
        if ($result instanceof Migration) {
            return $result;
        }

        // 风格二:由文件名推导类名实例化
        if (!class_exists($class, false)) {
            throw new \RuntimeException("迁移文件 [$name] 未找到可实例化的迁移类 [$class]");
        }

        // 类必须由当前文件定义,防止执行到其他文件的同名迁移
        $definedIn = (string)(new \ReflectionClass($class))->getFileName();
        if (realpath($definedIn) !== realpath($file)) {
            throw new \RuntimeException("迁移类名冲突: [$class] 已由 [" . basename($definedIn) . "] 定义,请重命名 [$name] 中的类");
        }

        return new $class();
    }

    protected function findFile(string $name): ?string
    {
        foreach ($this->migrationFiles() as $file) {
            if (basename($file, '.php') === $name) {
                return $file;
            }
        }
        return null;
    }
}
