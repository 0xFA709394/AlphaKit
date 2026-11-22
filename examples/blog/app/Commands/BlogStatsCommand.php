<?php

declare(strict_types=1);

namespace App\Commands;

use AlphaKit\Console\Command;
use App\Models\{Comment, Post, User};
use AlphaKit\Database\QueryBuilder;

/**
 * php bin/console blog:stats —— 自定义命令 + 聚合/表格输出演示
 */
final class BlogStatsCommand extends Command
{
    protected string $signature = 'blog:stats';
    protected string $description = '输出博客统计(文章/评论/浏览)';

    protected function handle(): ?int
    {
        $topViewed = Post::query()->orderBy('views', 'desc')->limit(3)->get();

        $this->table(['指标', '值'], [
            ['作者数', (string)User::count()],
            ['文章数', (string)Post::count()],
            ['已发布', (string)Post::query()->where('published', '=', 1)->count()],
            ['评论数', (string)Comment::count()],
            ['总浏览', (string)(Post::query()->sum('views') ?? 0)],
        ]);

        $this->line('');
        $this->info('浏览 Top 3:');
        $rows = array_map(fn ($p) => [(string)$p->id, (string)$p->title, (string)$p->views], $topViewed);
        $this->table(['ID', '标题', '浏览'], $rows ?: [['-', '(无)', '0']]);
        return self::SUCCESS;
    }
}
