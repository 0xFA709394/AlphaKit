<?php

declare(strict_types=1);

/**
 * Blog 示例种子数据:php bin/console db:seed
 */

use App\Models\{Comment, Post, User};

return function (): void {
    if (User::query()->where('email', '=', 'author@blog.dev')->first() === null) {
        User::create([
            'name' => '示例作者',
            'email' => 'author@blog.dev',
            'password' => password_hash('blog1234', PASSWORD_DEFAULT),
        ]);
        echo "已创建作者: author@blog.dev / blog1234\n";
    }

    $author = User::query()->where('email', '=', 'author@blog.dev')->first();

    if (Post::count() === 0) {
        $posts = [
            ['AlphaBlog 示例项目欢迎你', 'welcome', '这个示例项目覆盖 AlphaKit 框架的每一个方面:路由/中间件/事件/队列/调度/缓存/下载/HTTP 客户端…… 从本篇开始探索。', 1, 12],
            ['查询构造器进阶:分页与聚合', 'query-builder', 'QueryBuilder 支持链式 where/in/like/between/group、join、聚合与分页。本站的"热门文章"就是 getOrSet 缓存 + paginate 的组合。', 1, 7],
            ['事件与队列:解耦业务利器', 'events-queue', '文章发布触发 PostPublished 事件,监听器写审计日志并派发 sitemap 生成任务——由 worker 异步完成。', 1, 5],
        ];
        foreach ($posts as [$title, $slug, $content, $published, $views]) {
            Post::create(['user_id' => $author->id, 'title' => $title, 'slug' => $slug, 'content' => $content, 'published' => $published, 'views' => $views]);
        }
        Comment::create(['post_id' => 1, 'author_name' => '读者小王', 'body' => '写得很清楚,学习了!']);
        Comment::create(['post_id' => 1, 'author_name' => '匿名访客', 'body' => '期待更多框架细节文章。']);
        echo "已创建 3 篇文章 + 2 条评论\n";
    }
};
