<?php

declare(strict_types=1);

namespace App\Commands;

use AlphaKit\Console\Command;
use App\Events\PostPublished;
use App\Models\Post;

/**
 * php bin/console posts:publish {id} —— CLI → 事件 → 监听 → 队列 全链路演示
 */
final class PostsPublishCommand extends Command
{
    protected string $signature = 'posts:publish {id : 文章 ID} {--unpublish : 反向操作(取消发布)}';
    protected string $description = '发布/取消发布文章并触发事件链路';

    protected function handle(): ?int
    {
        $post = Post::findOrFail((int)$this->argument('id'));

        if ($this->option('unpublish')) {
            $post->update(['published' => 0]);
            $this->warn("文章 #{$post->id} 已取消发布");
            return self::SUCCESS;
        }

        $post->update(['published' => 1]);

        // 派发事件 → AppServiceProvider 注册的两个监听器执行:
        //   1) WriteAuditLog 同步写日志
        //   2) QueueSitemapGeneration 派发 GenerateSitemap 队列任务
        app(\AlphaKit\Core\Event\EventDispatcher::class)
            ->dispatch(new PostPublished((int)$post->id, (string)$post->title));

        $this->info("文章 #{$post->id}「{$post->title}」已发布");
        $this->line('  ↳ 事件已派发:审计日志 + sitemap 队列任务');
        return self::SUCCESS;
    }
}
