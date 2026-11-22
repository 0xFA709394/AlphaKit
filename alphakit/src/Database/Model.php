<?php

declare(strict_types=1);

namespace AlphaKit\Database;

/**
 * Active Record 模型基类
 *
 * class User extends \AlphaKit\Database\Model {
 *     protected string $table = 'users';
 *     protected array $hidden = ['password'];
 *     protected array $casts = ['is_admin' => 'bool'];
 * }
 *
 * User::find(1); User::where('name', 'ak')->first();
 * $u = new User(['name' => 'ak']); $u->save(); $u->delete();
 * $u->tasks  // hasMany
 */
abstract class Model implements \JsonSerializable
{
    /** 表名,未指定时根据类名蛇形复数推断 */
    protected string $table = '';
    /** 主键字段 */
    protected string $primaryKey = 'id';
    /** 序列化时隐藏的字段 */
    protected array $hidden = [];
    /** 类型转换: ['age' => 'int', 'tags' => 'json', 'created_at' => 'datetime'] */
    protected array $casts = [];
    /** 是否维护 created_at / updated_at */
    protected bool $timestamps = true;

    /** @var array<string, mixed> 属性 */
    protected array $attributes = [];

    /** @var array<string, mixed> 原始属性(更新检测) */
    protected array $original = [];

    /** @var array 已加载的关联 */
    protected array $relations = [];

    /** @var bool 是否已存在于数据库 */
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ------------------------------------------------------------- 查询 API

    public static function query(): QueryBuilder
    {
        return static::newQueryBuilder()->from((new static())->getTable());
    }

    /** 按主键查询 */
    public static function find($id): ?static
    {
        $model = new static();
        $row = static::query()->where($model->primaryKey, '=', $id)->first();
        if ($row === null) {
            return null;
        }
        return $row instanceof static ? $row : static::hydrate($row);
    }

    /** 按主键查询或抛 404 */
    public static function findOrFail($id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new \AlphaKit\Core\Exception\NotFoundException(static::class . " #$id 不存在");
        }
        return $model;
    }

    /** 全部记录 */
    public static function all(): array
    {
        return array_map([static::class, 'hydrate'], static::query()->get());
    }

    public static function where(string $column, $operatorOrValue, $value = null): QueryBuilder
    {
        return static::query()->where($column, $operatorOrValue, $value);
    }

    public static function orderBy(string $column, string $direction = 'asc'): QueryBuilder
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function limit(int $limit): QueryBuilder
    {
        return static::query()->limit($limit);
    }

    /** 创建并保存 */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    // ------------------------------------------------------------- 实例 API

    /** 批量赋值 */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /** 保存(INSERT 或 UPDATE) */
    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');
        if ($this->timestamps) {
            if (!$this->exists) {
                $this->attributes['created_at'] = $this->attributes['created_at'] ?? $now;
            }
            $this->attributes['updated_at'] = $now;
        }

        $connection = static::connection();

        if ($this->exists) {
            // 严格比较检测脏字段(array_diff_assoc 是松散比较,'1' 与 1 会误判为相同)
            $dirty = array_filter(
                $this->attributes,
                fn ($key) => !array_key_exists($key, $this->original) || $this->original[$key] !== $this->attributes[$key],
                ARRAY_FILTER_USE_KEY
            );
            if ($dirty === []) {
                return true; // 无变更
            }
            $pk = $this->primaryKey;
            $connection->update($this->getTable(), $dirty, $connection->wrap($pk) . ' = ?', [$this->attributes[$pk]]);
        } else {
            $data = $this->attributes;
            $id = $connection->insert($this->getTable(), $data);
            if (($this->attributes[$this->primaryKey] ?? null) === null) {
                $this->attributes[$this->primaryKey] = $id;
            }
            $this->exists = true;
        }

        $this->original = $this->attributes;
        return true;
    }

    /** 删除 */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $connection = static::connection();
        $connection->delete($this->getTable(), $connection->wrap($this->primaryKey) . ' = ?', [$this->attributes[$this->primaryKey]]);
        $this->exists = false;
        return true;
    }

    /** 更新并保存 */
    public function update(array $attributes): static
    {
        $this->fill($attributes)->save();
        return $this;
    }

    // ------------------------------------------------------------- 关联

    /** 一对多:当前模型主键 -> 外键 */
    public function hasMany(string $relatedClass, string $foreignKey = '', string $localKey = ''): array
    {
        $foreignKey = $foreignKey ?: $this->snakyClassName() . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        return $relatedClass::query()->where($foreignKey, '=', $this->attributes[$localKey])->get();
    }

    /**
     * 多对一:外键 -> 关联表主键
     * 返回关联模型实例(类型由子类方法签名约束)
     */
    public function belongsTo(string $relatedClass, string $foreignKey = '', string $ownerKey = '')
    {
        $ownerKey = $ownerKey ?: 'id';
        if ($foreignKey === '') {
            $short = (new \ReflectionClass($relatedClass))->getShortName();
            $foreignKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short)) . '_id';
        }
        $row = $relatedClass::query()->where($ownerKey, '=', $this->attributes[$foreignKey] ?? null)->first();
        if ($row === null) {
            return null;
        }
        return $row instanceof $relatedClass ? $row : $relatedClass::hydrate($row);
    }

    /** 缓存式关联访问 */
    public function relation(string $name): mixed
    {
        if (!array_key_exists($name, $this->relations)) {
            $this->relations[$name] = $this->$name();
        }
        return $this->relations[$name];
    }

    // ------------------------------------------------------------- 输出

    /** 转数组(应用 casts / hidden,递归关联) */
    public function toArray(): array
    {
        $data = $this->attributes;
        foreach ($this->hidden as $hidden) {
            unset($data[$hidden]);
        }
        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $data[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $data[$key] = array_map(fn ($v) => $v instanceof self ? $v->toArray() : $v, $value);
            } else {
                $data[$key] = $this->castAttribute($key, $value);
            }
        }
        foreach ($this->relations as $name => $relation) {
            $data[$name] = is_array($relation)
                ? array_map(fn ($r) => $r instanceof self ? $r->toArray() : $r, $relation)
                : ($relation instanceof self ? $relation->toArray() : $relation);
        }
        return $data;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function castAttribute(string $key, $value)
    {
        $cast = $this->casts[$key] ?? null;
        if ($cast === null || $value === null) {
            return $value;
        }
        switch ($cast) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'string':
                return (string)$value;
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'array':
                return is_string($value) ? explode(',', $value) : $value;
            default:
                return $value;
        }
    }

    // ------------------------------------------------------------- 基础设施

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }
        // 类名 -> 蛇形复数: App\Models\UserTask -> user_tasks
        $short = (new \ReflectionClass($this))->getShortName();
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        $this->table = $snake . (str_ends_with($snake, 's') ? '' : 's');
        return $this->table;
    }

    protected function snakyClassName(): string
    {
        $short = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    }

    /** 从数据库行构造模型 */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;
        return $model;
    }

    protected static function connection(): Connection
    {
        return app(Connection::class);
    }

    protected static function newQueryBuilder(): QueryBuilder
    {
        return app(QueryBuilder::class)->useModel(new static());
    }

    /** 魔术访问:属性优先,其次关联方法 */
    public function __get(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        if (method_exists($this, $key)) {
            return $this->relation($key);
        }
        return null;
    }

    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || method_exists($this, $key);
    }
}
