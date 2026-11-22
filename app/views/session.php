<?php
/** @var int $visits */
/** @var string $flash */
/** @var string $sessionId */

$this->extend('layouts/app');
$this->start('title'); ?>Session 演示 - AlphaKit<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>Session 演示</h1>
    <?php if ($flash !== ''): ?>
        <div class="flash"><?= e($flash) ?></div>
    <?php endif; ?>
    <p>你是本会话第 <strong><?= (int)$visits ?></strong> 次访问本页。</p>
    <p>会话 ID(前 8 位): <code><?= e($sessionId) ?></code></p>
    <p><a href="/demo/session">再刷新一次</a> — 刷新计数增加,Flash 消息只在下一次请求出现。</p>
</div>
<?php $this->end(); ?>
