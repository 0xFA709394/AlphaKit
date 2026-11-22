<?php

declare(strict_types=1);

namespace AlphaKit\Database;

/**
 * PDO 连接封装
 * 支持 sqlite / mysql,按 config/database.php 的 default 连接配置初始化
 */
class Connection
{
    protected ?\PDO $pdo = null;

    /** @var array 最近一条 SQL 与参数(调试用) */
    public array $lastQuery = [];

    public function __construct(protected array $config = [])
    {
    }

    /** 懒初始化 PDO */
    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $config = $this->config ?: (array)config('database.connections.' . config('database.default', 'sqlite'), []);
            $driver = $config['driver'] ?? 'sqlite';

            if ($driver === 'sqlite') {
                $path = $config['database'] ?? database_path('database.sqlite');
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $dsn = 'sqlite:' . $path;
                $this->pdo = new \PDO($dsn);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            } elseif ($driver === 'mysql') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $config['host'] ?? '127.0.0.1',
                    $config['port'] ?? 3306,
                    $config['database'] ?? '',
                    $config['charset'] ?? 'utf8mb4'
                );
                $this->pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '', [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
            } else {
                throw new \RuntimeException("不支持的数据库驱动 [$driver],当前支持: sqlite / mysql");
            }

            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        }
        return $this->pdo;
    }

    /**
     * 执行写操作(INSERT/UPDATE/DELETE/DDL),返回受影响行数
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $this->lastQuery = ['sql' => $sql, 'bindings' => $bindings];
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * 查询多行
     *
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $this->lastQuery = ['sql' => $sql, 'bindings' => $bindings];
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /**
     * 查询单行
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $rows = $this->select($sql, $bindings);
        return $rows[0] ?? null;
    }

    /**
     * 查询单值(COUNT/SUM 等)
     *
     * @return mixed
     */
    public function scalar(string $sql, array $bindings = [])
    {
        $this->lastQuery = ['sql' => $sql, 'bindings' => $bindings];
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchColumn();
    }

    /** INSERT 并返回自增 ID */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->wrap($table),
            implode(', ', array_map([$this, 'wrap'], $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->execute($sql, array_values($data));
        return (int)$this->pdo()->lastInsertId();
    }

    /** UPDATE,返回受影响行数 */
    public function update(string $table, array $data, string $where, array $whereBindings = []): int
    {
        $sets = implode(', ', array_map(fn ($c) => $this->wrap($c) . ' = ?', array_keys($data)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->wrap($table), $sets, $where);
        return $this->execute($sql, array_merge(array_values($data), $whereBindings));
    }

    /** DELETE,返回受影响行数 */
    public function delete(string $table, string $where, array $bindings = []): int
    {
        return $this->execute(sprintf('DELETE FROM %s WHERE %s', $this->wrap($table), $where), $bindings);
    }

    /** 开启事务 */
    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    /** 事务闭包 */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /** 标识符转义 */
    public function wrap(string $identifier): string
    {
        $driver = $this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (str_contains($identifier, '(') || str_contains($identifier, '.')) {
            return $identifier; // 函数或限定名不转义
        }
        return $driver === 'mysql' ? '`' . $identifier . '`' : '"' . $identifier . '"';
    }

    /** 表是否存在 */
    public function tableExists(string $table): bool
    {
        $driver = $this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $count = $this->scalar(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
        } else {
            $count = $this->scalar(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                [$table]
            );
        }
        return (int)$count > 0;
    }
}
