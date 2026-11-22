<?php

declare(strict_types=1);

namespace AlphaKit\Database\Schema;

use AlphaKit\Database\Connection;

/**
 * 结构构造器:建表 / 删表 / 加列 / 索引
 *
 * Schema::create('users', function (Blueprint $t) { ... });
 * Schema::drop('users');
 * Schema::hasTable('users');
 */
class Schema
{
    public static function create(string $table, callable $callback): void
    {
        /** @var Connection $connection */
        $connection = app(Connection::class);
        $blueprint = new Blueprint();
        $callback($blueprint);

        $connection->execute($blueprint->toSql($table));
        foreach ($blueprint->toIndexSql($table) as $sql) {
            $connection->execute($sql);
        }
    }

    public static function drop(string $table): void
    {
        app(Connection::class)->execute('DROP TABLE IF EXISTS ' . app(Connection::class)->wrap($table));
    }

    public static function dropIfExists(string $table): void
    {
        if (static::hasTable($table)) {
            static::drop($table);
        }
    }

    public static function hasTable(string $table): bool
    {
        return app(Connection::class)->tableExists($table);
    }

    /** 重命名表 */
    public static function rename(string $from, string $to): void
    {
        $connection = app(Connection::class);
        $driver = $connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $connection->execute("RENAME TABLE `{$from}` TO `{$to}`");
        } else {
            $connection->execute("ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"");
        }
    }
}
