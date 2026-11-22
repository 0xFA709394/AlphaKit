<?php

declare(strict_types=1);

namespace AlphaKit\Database;

/**
 * 查询构造器(链式)
 *
 * 示例:
 *   DB::table('users')->where('active', 1)->orderBy('id', 'desc')->limit(10)->get()
 *   DB::table('users')->where('age', '>', 18)->whereLike('name', '%ak%')->first()
 *   DB::table('users')->insert(['name' => 'ak'])
 *   DB::table('users')->where('id', 1)->update(['name' => 'new'])
 *   DB::table('users')->where('id', 1)->delete()
 *   DB::table('users')->paginate(15)
 */
class QueryBuilder
{
    protected string $table = '';
    /** @var array<int, array{type: string, column: string, operator: string|null, value: mixed, boolean: string}> */
    protected array $wheres = [];
    /** @var array<int, array{type: string, sql: string, bindings: array}> */
    protected array $joins = [];
    protected array $columns = ['*'];
    protected array $orderBy = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $groupBy = [];
    protected ?string $having = null;
    protected array $havingBindings = [];

    /** @var Model|null 绑定模型(get/first/paginate 结果自动 hydrate) */
    protected ?Model $model = null;

    public function __construct(protected Connection $connection)
    {
    }

    public static function table(string $table): self
    {
        return app(self::class)->from($table);
    }

    /** 绑定模型(结果自动转为模型实例并应用 casts/hidden) */
    public function useModel(?Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(string ...$columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /** WHERE column = value */
    public function where(string $column, $operatorOrValue, $value = null): self
    {
        [$operator, $value] = $value === null ? ['=', $operatorOrValue] : [$operatorOrValue, $value];
        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    public function orWhere(string $column, $operatorOrValue, $value = null): self
    {
        [$operator, $value] = $value === null ? ['=', $operatorOrValue] : [$operatorOrValue, $value];
        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value, 'boolean' => 'OR'];
        return $this;
    }

    /** WHERE column IN (...) */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'operator' => 'IN', 'value' => $values, 'boolean' => 'AND'];
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = ['type' => 'in', 'column' => $column, 'operator' => 'NOT IN', 'value' => $values, 'boolean' => 'AND'];
        return $this;
    }

    /** WHERE column LIKE */
    public function whereLike(string $column, string $pattern): self
    {
        $this->wheres[] = ['type' => 'basic', 'column' => $column, 'operator' => 'LIKE', 'value' => $pattern, 'boolean' => 'AND'];
        return $this;
    }

    /** WHERE column BETWEEN a AND b */
    public function whereBetween(string $column, $a, $b): self
    {
        $this->wheres[] = ['type' => 'between', 'column' => $column, 'operator' => 'BETWEEN', 'value' => [$a, $b], 'boolean' => 'AND'];
        return $this;
    }

    /** WHERE column IS NULL */
    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'operator' => 'IS NULL', 'value' => null, 'boolean' => 'AND'];
        return $this;
    }

    /** 嵌套 OR 条件组 */
    public function whereGroup(callable $callback): self
    {
        $sub = new self($this->connection);
        $callback($sub);
        $this->wheres[] = ['type' => 'group', 'column' => $sub, 'operator' => null, 'value' => null, 'boolean' => 'AND'];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = ['type' => 'INNER', 'sql' => sprintf('INNER JOIN %s ON %s %s %s', $this->connection->wrap($table), $first, $operator, $second), 'bindings' => []];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = ['type' => 'LEFT', 'sql' => sprintf('LEFT JOIN %s ON %s %s %s', $this->connection->wrap($table), $first, $operator, $second), 'bindings' => []];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orderBy[] = $this->connection->wrap($column) . ' ' . $direction;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    public function having(string $sql, array $bindings = []): self
    {
        $this->having = $sql;
        $this->havingBindings = $bindings;
        return $this;
    }

    // ------------------------------------------------------------------ 读

    /** 查询多行(绑定模型时返回模型实例) */
    public function get(): array
    {
        [$sql, $bindings] = $this->toSql();
        $rows = $this->connection->select($sql, $bindings);
        return $this->model !== null
            ? array_map(fn (array $row) => $this->model::hydrate($row), $rows)
            : $rows;
    }

    /** 第一行(绑定模型时返回模型实例,否则原始数组) */
    public function first(): mixed
    {
        $this->limit(1);
        return $this->get()[0] ?? null;
    }

    /** 指定列的第一值 */
    public function value(string $column)
    {
        $row = $this->first();
        if ($row instanceof Model) {
            return $row->getAttribute($column);
        }
        return $row[$column] ?? null;
    }

    /** COUNT(*) */
    public function count(): int
    {
        return (int)$this->aggregate('COUNT', '*');
    }

    public function sum(string $column)
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column)
    {
        return $this->aggregate('AVG', $column);
    }

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    /** EXISTS */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    protected function aggregate(string $function, string $column)
    {
        $columns = $this->columns;
        $this->columns = [$function === 'COUNT' && $column === '*' ? 'COUNT(*)' : sprintf('%s(%s)', $function, $this->connection->wrap($column))];
        [$sql, $bindings] = $this->toSql();
        $this->columns = $columns;
        return $this->connection->scalar($sql, $bindings);
    }

    /**
     * 分页查询
     *
     * @return array{data: array, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $total = $this->count();

        $this->limit($perPage)->offset(($page - 1) * $perPage);
        return [
            'data' => $this->get(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    /** chunk 分批处理大数据 */
    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        while (true) {
            $rows = (clone $this)->limit($size)->offset($offset)->get();
            if ($rows === []) {
                break;
            }
            if ($callback($rows) === false) {
                break;
            }
            $offset += $size;
        }
    }

    // ------------------------------------------------------------------ 写

    /** INSERT 单行,返回自增 ID */
    public function insert(array $data): int
    {
        return $this->connection->insert($this->table, $data);
    }

    /** INSERT 多行 */
    public function insertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $columns = array_keys($rows[0]);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->connection->wrap($this->table),
            implode(', ', array_map([$this->connection, 'wrap'], $columns)),
            implode(', ', array_fill(0, count($rows), $placeholders))
        );
        $bindings = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }
        $this->connection->execute($sql, $bindings);
    }

    /** UPDATE(需先 where),返回受影响行数 */
    public function update(array $data): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException('禁止无 WHERE 条件的全表 UPDATE');
        }
        $sets = implode(', ', array_map(fn ($c) => $this->connection->wrap($c) . ' = ?', array_keys($data)));
        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = sprintf('UPDATE %s SET %s %s', $this->connection->wrap($this->table), $sets, $whereSql);
        return $this->connection->execute($sql, array_merge(array_values($data), $whereBindings));
    }

    /** 自增/自减 */
    public function increment(string $column, int $amount = 1): int
    {
        return $this->updateRaw([$column => [$column, $amount, '+']]);
    }

    public function decrement(string $column, int $amount = 1): int
    {
        return $this->updateRaw([$column => [$column, $amount, '-']]);
    }

    /** 原子更新 column = column + n 等 */
    protected function updateRaw(array $expressions): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException('禁止无 WHERE 条件的全表更新');
        }
        $sets = [];
        $bindings = [];
        foreach ($expressions as $expr) {
            [$column, $amount, $op] = $expr;
            $sets[] = sprintf('%s = %s %s ?', $this->connection->wrap($column), $this->connection->wrap($column), $op);
            $bindings[] = $amount;
        }
        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = sprintf('UPDATE %s SET %s %s', $this->connection->wrap($this->table), implode(', ', $sets), $whereSql);
        return $this->connection->execute($sql, array_merge($bindings, $whereBindings));
    }

    /** DELETE(需先 where) */
    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException('禁止无 WHERE 条件的全表 DELETE');
        }
        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql = sprintf('DELETE FROM %s %s', $this->connection->wrap($this->table), $whereSql);
        return $this->connection->execute($sql, $whereBindings);
    }

    /** UPSERT(sqlite 3.24+/mysql) */
    public function upsert(array $data, array $uniqueColumns): void
    {
        $columns = array_keys($data);
        $updates = implode(', ', array_map(fn ($c) => $this->connection->wrap($c) . ' = ?', $columns));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s',
            $this->connection->wrap($this->table),
            implode(', ', array_map([$this->connection, 'wrap'], $columns)),
            implode(', ', array_fill(0, count($columns), '?')),
            implode(', ', array_map([$this->connection, 'wrap'], $uniqueColumns)),
            $updates
        );
        $this->connection->execute($sql, array_merge(array_values($data), array_values($data)));
    }

    // ------------------------------------------------------------------ 编译

    /** 编译 SELECT 语句 [sql, bindings] */
    public function toSql(): array
    {
        $columns = implode(', ', array_map(
            fn ($c) => $c === '*' ? '*' : $this->connection->wrap($c),
            $this->columns
        ));

        $sql = sprintf('SELECT %s FROM %s', $columns, $this->connection->wrap($this->table));

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join['sql'];
        }

        [$whereSql, $whereBindings] = $this->compileWheres();
        $sql .= $whereSql;

        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', array_map([$this->connection, 'wrap'], $this->groupBy));
        }
        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
            $whereBindings = array_merge($whereBindings, $this->havingBindings);
        }
        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return [$sql, $whereBindings];
    }

    /** @return array{0: string, 1: array} */
    protected function compileWheres(): array
    {
        if ($this->wheres === []) {
            return ['', []];
        }

        $sql = '';
        $bindings = [];
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : ' ' . $where['boolean'] . ' ';
            switch ($where['type']) {
                case 'basic':
                    $sql .= $boolean . $this->connection->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
                    $bindings[] = $where['value'];
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['value']), '?'));
                    $sql .= $boolean . $this->connection->wrap($where['column']) . ' ' . $where['operator'] . ' (' . $placeholders . ')';
                    foreach ((array)$where['value'] as $v) {
                        $bindings[] = $v;
                    }
                    break;
                case 'between':
                    $sql .= $boolean . $this->connection->wrap($where['column']) . ' BETWEEN ? AND ?';
                    $bindings[] = $where['value'][0];
                    $bindings[] = $where['value'][1];
                    break;
                case 'null':
                    $sql .= $boolean . $this->connection->wrap($where['column']) . ' ' . $where['operator'];
                    break;
                case 'group':
                    /** @var self $sub */
                    $sub = $where['column'];
                    [$subSql, $subBindings] = $sub->compileWheres();
                    $sql .= $boolean . '(' . trim((string)preg_replace('/^\s*WHERE/i', '', $subSql)) . ')';
                    $bindings = array_merge($bindings, $subBindings);
                    break;
            }
        }

        return [' WHERE ' . ltrim($sql), $bindings];
    }

    public function __clone()
    {
        // chunk/paginate 中克隆以保留条件
    }
}
