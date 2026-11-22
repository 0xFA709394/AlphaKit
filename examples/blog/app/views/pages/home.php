<?php
/** @var array $posts */
/** @var string|null $sessionUser */

$this->extend('layouts/blog');
$this->start('title'); ?><?= \e(config('app.name')) ?> - 首页<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>📝 <?= \e(config('app.name')) ?> <span class="tag">AlphaKit 示例项目</span></h1>
    <p class="muted">覆盖框架全部用法:路由/中间件/事件/队列/调度/缓存/JWT/下载/HttpClient/CLI……</p>
    <?php if ($sessionUser !== null): ?>
        <p>当前会话用户:<strong><?= \e($sessionUser) ?></strong>
            <form class="inline" method="post" action="/logout"><button>退出</button></form>
        </p>
    <?php endif; ?>
</div>

<?php foreach ($posts as $card): ?>
    <?= $this->insert('partials/post-card', ['card' => $card]) ?>
<?php endforeach; ?>
<?php $this->end(); ?>
