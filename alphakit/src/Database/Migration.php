<?php

declare(strict_types=1);

namespace AlphaKit\Database;

/**
 * 迁移基类
 *
 * class CreateUsersTable extends Migration {
 *     public function up(): void {
 *         Schema::create('users', function (Blueprint $t) { $t->id(); ... });
 *     }
 *     public function down(): void { Schema::drop('users'); }
 * }
 */
abstract class Migration
{
    abstract public function up(): void;

    public function down(): void
    {
        // 默认空实现,可覆盖
    }
}
