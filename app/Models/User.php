<?php

declare(strict_types=1);

namespace App\Models;

use AlphaKit\Database\Model;

/**
 * 用户模型
 */
final class User extends Model
{
    protected string $table = 'users';

    /** 序列化时隐藏敏感字段 */
    protected array $hidden = ['password'];

    protected array $casts = [
        'is_active' => 'bool',
    ];

    /** 用户的任务(一对多) */
    public function tasks(): array
    {
        return $this->hasMany(Task::class);
    }
}
