<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * 欢迎邮件任务(队列演示)
 *
 * 注册接口派发后由 worker 异步消费:
 *   php bin/alphakit queue:work --once
 */
final class SendWelcomeEmail
{
    public int $tries = 3;
    public int $retryAfter = 5;

    public function __construct(protected array $payload = [])
    {
    }

    public function handle(): void
    {
        // 演示:真实场景接入邮件服务(如 SMTP / 第三方 API)
        logger('mail')->info('欢迎邮件已发送', [
            'to' => $this->payload['email'] ?? null,
            'user_id' => $this->payload['user_id'] ?? null,
            'at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger('mail')->error('欢迎邮件最终发送失败', [
            'payload' => $this->payload,
            'error' => $e->getMessage(),
        ]);
    }
}
