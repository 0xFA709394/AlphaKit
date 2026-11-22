<?php
/** @var int $page */
/** @var array $posts */

$this->extend('layouts/blog');
$this->start('title'); ?>归档 - AlphaBlog<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>归档 · 第 <?= (int)$page ?> 页</h1>
    <p class="muted">可选路由参数 {page?}:不带参数访问 <code>/archive</code> 默认第 1 页。</p>
    <?php foreach ($posts as $p): ?>
        <p>· <a href="<?= \url('post.show', ['id' => $p->id]) ?>"><?= \e($p->title) ?></a></p>
    <?php endforeach; ?>
    <?php if ($posts === []): ?><p class="muted">本页无内容</p><?php endif; ?>
</div>
<?php $this->end(); ?>
