<?php
/** @var \App\Models\Post $post */
/** @var array $comments */
/** @var \App\Models\User|null $author */

$this->extend('layouts/blog');
$this->start('title'); ?><?= \e($post->title) ?><?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1><?= \e($post->title) ?></h1>
    <div class="muted">
        👁 <?= (int)$post->views ?> 次浏览 ·
        作者: <?= \e($author->name ?? '未知') ?> ·
        slug: <code><?= \e($post->slug) ?></code> ·
        <a href="<?= \url('post.export', ['id' => $post->id]) ?>">导出 Markdown ↓</a> ·
        <a href="<?= \url('post.go', ['id' => $post->id]) ?>">重定向演示 ↗</a>
    </div>
    <p style="margin-top:10px;white-space:pre-wrap"><?= \e($post->content) ?></p>
</div>

<div class="card">
    <h2 style="font-size:16px">评论(<?= count($comments) ?>)</h2>
    <?php foreach ($comments as $c): ?>
        <p><strong><?= \e($c->author_name) ?></strong>:<?= \e($c->body) ?>
            <span class="muted"><?= \e($c->created_at) ?></span></p>
    <?php endforeach; ?>
    <?php if ($comments === []): ?>
        <p class="muted">还没有评论,POST /api/posts/<?= (int)$post->id ?>/comments 提交一条。</p>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
