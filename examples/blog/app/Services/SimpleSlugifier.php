<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SlugifierInterface;

final class SimpleSlugifier implements SlugifierInterface
{
    public function make(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '-', $slug) ?? $slug;
        return trim($slug, '-') ?: 'post-' . time();
    }
}
