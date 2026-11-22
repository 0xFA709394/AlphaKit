<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * 队列任务:评论通知(重试 + 失败兜底演示)
 */
final class SendCommentNotification
{
    public int $tries = 3;
    public int $retryAfter = 5;

    public function __construct(protected array $payload = [])
    {
    }

    public function handle(): void
    {
        logger('mail')->info('评论通知已发送', [
            'post_id' => $this->payload['post_id'] ?? null,
            'to_author' => $this->payload['author_email'] ?? null,
            'at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger('mail')->error('评论通知最终失败', ['error' => $e->getMessage()]);
    }
}
