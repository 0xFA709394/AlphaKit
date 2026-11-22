<?php

declare(strict_types=1);

namespace App\Models;

use AlphaKit\Database\Model;

/**
 * 任务模型
 */
final class Task extends Model
{
    protected string $table = 'tasks';

    protected array $casts = [
        'done' => 'bool',
        'tags' => 'json',
    ];

    /** 归属用户(多对一) */
    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
}
