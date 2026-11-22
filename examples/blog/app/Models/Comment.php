<?php

declare(strict_types=1);

namespace App\Models;

use AlphaKit\Database\Model;

final class Comment extends Model
{
    protected string $table = 'comments';

    public function post(): ?Post
    {
        return $this->belongsTo(Post::class);
    }
}
