<?php

declare(strict_types=1);

namespace App\Models;

use AlphaKit\Database\Model;

final class Post extends Model
{
    protected string $table = 'posts';
    protected array $casts = ['published' => 'bool', 'views' => 'int'];

    public function comments(): array
    {
        return $this->hasMany(Comment::class);
    }

    public function author(): ?User
    {
        return $this->belongsTo(User::class);
    }

    /** 评论数聚合(QueryBuilder::count 演示) */
    public function commentCount(): int
    {
        return \AlphaKit\Database\QueryBuilder::table('comments')->where('post_id', '=', $this->id)->count();
    }
}
