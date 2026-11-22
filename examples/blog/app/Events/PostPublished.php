<?php

declare(strict_types=1);

namespace App\Events;

/**
 * 事件对象:文章发布(事件系统演示)
 */
final class PostPublished
{
    public function __construct(public readonly int $postId, public readonly string $title)
    {
    }
}
