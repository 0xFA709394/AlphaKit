<?php

declare(strict_types=1);

namespace App\Models;

use AlphaKit\Database\Model;

final class User extends Model
{
    protected string $table = 'users';
    protected array $hidden = ['password'];

    public function posts(): array
    {
        return $this->hasMany(Post::class);
    }
}
