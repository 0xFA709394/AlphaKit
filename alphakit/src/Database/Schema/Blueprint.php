<?php

declare(strict_types=1);

namespace AlphaKit\Database\Schema;

/**
 * 表结构定义(建表蓝图)
 * 自动适配 sqlite / mysql 语法
 */
class Blueprint
{
    /** @var array<int, string> DDL 片段 */
    public array $columns = [];

    /** @var array<int, string> 索引/约束 DDL(兼容保留) */
    public array $indexes = [];

    /** @var array<int, array<int, string>> 普通索引列组 */
    public array $indexGroups = [];

    /** @var array<int, array<int, string>> 唯一约束列组 */
    public array $uniqueGroups = [];

    public string $engine = '';

    /** 自增主键 */
    public function id(string $name = 'id'): self
    {
        $this->columns[] = $this->driver() === 'mysql'
            ? "`{$name}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY"
            : "\"{$name}\" INTEGER PRIMARY KEY AUTOINCREMENT";
        return $this;
    }

    /** 普通整型主键 */
    public function integer(string $name, bool $autoIncrement = false): self
    {
        if ($autoIncrement) {
            $this->columns[] = $this->driver() === 'mysql'
                ? "`{$name}` INT NOT NULL AUTO_INCREMENT"
                : "\"{$name}\" INTEGER NOT NULL";
        } else {
            $this->col($name, $this->driver() === 'mysql' ? 'INT' : 'INTEGER');
        }
        return $this;
    }

    public function bigInteger(string $name): self
    {
        return $this->integer($name);
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->col($name, $this->driver() === 'mysql' ? "VARCHAR({$length})" : 'TEXT');
    }

    public function text(string $name): self
    {
        return $this->col($name, 'TEXT');
    }

    public function boolean(string $name): self
    {
        return $this->col($name, $this->driver() === 'mysql' ? 'TINYINT(1)' : 'INTEGER', '0');
    }

    public function float(string $name): self
    {
        return $this->col($name, 'REAL');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        return $this->col($name, "DECIMAL({$precision},{$scale})");
    }

    public function datetime(string $name): self
    {
        return $this->col($name, $this->driver() === 'mysql' ? 'DATETIME' : 'TEXT');
    }

    public function date(string $name): self
    {
        return $this->col($name, $this->driver() === 'mysql' ? 'DATE' : 'TEXT');
    }

    public function json(string $name): self
    {
        return $this->col($name, $this->driver() === 'mysql' ? 'JSON' : 'TEXT');
    }

    /** 维护 created_at / updated_at */
    public function timestamps(): self
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
        return $this;
    }

    /** 软删除标记列 */
    public function softDeletes(): self
    {
        return $this->datetime('deleted_at')->nullable();
    }

    protected function col(string $name, string $type, ?string $default = null): self
    {
        $ddl = $this->driver() === 'mysql' ? "`{$name}` {$type} NOT NULL" : "\"{$name}\" {$type} NOT NULL";
        if ($default !== null) {
            $ddl .= " DEFAULT '{$default}'";
        }
        $this->columns[] = $ddl;
        return $this;
    }

    public function nullable(): self
    {
        $last = array_pop($this->columns);
        $last = str_replace('NOT NULL', 'NULL', $last);
        // 去掉默认值中的 NOT NULL 约束位置问题:先替换再追加
        $this->columns[] = $last;
        return $this;
    }

    public function default($value): self
    {
        $last = array_pop($this->columns);
        $formatted = is_string($value) ? "'{$value}'" : (is_bool($value) ? (int)$value : $value);
        $this->columns[] = $last . " DEFAULT {$formatted}";
        return $this;
    }

    public function unique(): self
    {
        // 取最近一列生成表级唯一索引(空列表时安全跳过)
        $last = $this->columns === [] ? '' : (string)end($this->columns);
        if (preg_match('/^(?:`|")(\w+)(?:`|")/', $last, $m)) {
            $this->uniqueIndex($m[1]);
        }
        return $this;
    }

    public function index(string ...$columns): self
    {
        $this->indexGroups[] = $columns;
        return $this;
    }

    public function uniqueIndex(string ...$columns): self
    {
        $this->uniqueGroups[] = $columns;
        return $this;
    }

    protected function driver(): string
    {
        return app(\AlphaKit\Database\Connection::class)->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /** 编译 CREATE TABLE 语句(唯一约束内联,普通索引独立生成) */
    public function toSql(string $table): string
    {
        $driver = $this->driver();
        $wrapped = $driver === 'mysql' ? "`{$table}`" : "\"{$table}\"";

        $parts = $this->columns;
        foreach ($this->uniqueGroups as $columns) {
            $cols = implode(', ', array_map(fn ($c) => $driver === 'mysql' ? "`{$c}`" : "\"{$c}\"", $columns));
            $parts[] = "UNIQUE ({$cols})";
        }

        $sql = sprintf('CREATE TABLE %s (%s)', $wrapped, implode(', ', $parts));
        if ($driver === 'mysql' && $this->engine !== '') {
            $sql .= ' ENGINE=' . $this->engine;
        }
        return $sql;
    }

    /** 普通索引的独立建索引语句 */
    public function toIndexSql(string $table): array
    {
        $driver = $this->driver();
        $wrapped = $driver === 'mysql' ? "`{$table}`" : "\"{$table}\"";

        $statements = [];
        foreach ($this->indexGroups as $columns) {
            $name = $table . '_' . implode('_', $columns) . '_idx';
            $cols = implode(', ', array_map(fn ($c) => $driver === 'mysql' ? "`{$c}`" : "\"{$c}\"", $columns));
            $nameWrapped = $driver === 'mysql' ? "`{$name}`" : "\"{$name}\"";
            $statements[] = sprintf('CREATE INDEX %s ON %s (%s)', $nameWrapped, $wrapped, $cols);
        }
        return $statements;
    }
}
