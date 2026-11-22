<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostPublished;

/**
 * 监听器一:同步写审计日志
 */
final class WriteAuditLog
{
    public function handle(PostPublished $event): void
    {
        logger('audit')->info('文章已发布', ['id' => $event->postId, 'title' => $event->title]);
    }
}
