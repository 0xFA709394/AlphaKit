<?php

declare(strict_types=1);

namespace App\Models;

use AlphaKit\Database\Model;

/**
 * Tag 模型(由 make:model 生成后填充)
 */
final class Tag extends Model
{
    protected string $table = 'tags';
    protected array $casts = ['weight' => 'int'];

    /** 权重总和(QueryBuilder::sum 演示) */
    public static function totalWeight(): int
    {
        return (int)(static::query()->sum('weight') ?? 0);
    }
}
