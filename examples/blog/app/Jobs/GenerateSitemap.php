<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Post;
use AlphaKit\Database\QueryBuilder;

/**
 * 队列任务:生成 sitemap(真实产物,可验证)
 */
final class GenerateSitemap
{
    public int $tries = 2;
    public int $retryAfter = 3;

    public function handle(): void
    {
        // QueryBuilder chunk 分批演示(大数据量不爆内存)
        $lines = ["# AlphaBlog sitemap " . now()];
        QueryBuilder::table('posts')->where('published', '=', 1)->orderBy('id')->chunk(50, function (array $rows) use (&$lines) {
            foreach ($rows as $post) {
                $lines[] = "/posts/{$post['id']}  {$post['title']}";
            }
            return true;
        });

        $dir = storage_path('app');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/sitemap.txt', implode("\n", $lines) . "\n", LOCK_EX);
        logger('sitemap')->info('sitemap 已生成', ['entries' => count($lines) - 1]);
    }
}
