<?php
/** @var int $visits */
/** @var string $sessionId */
/** @var int $keyCount */
/** @var bool $loggedIn */

$this->extend('layouts/blog');
$this->start('title'); ?>Session 演示 - AlphaBlog<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>Session 演示</h1>
    <p>本会话第 <strong><?= (int)$visits ?></strong> 次访问。</p>
    <p>会话 ID 前 8 位:<code><?= \e($sessionId) ?></code>(登录时 regenerate 轮换)</p>
    <p>会话键数量:<?= (int)$keyCount ?> · 已登录:<?= $loggedIn ? '是' : '否' ?>(has() 演示)</p>
    <p><a href="<?= \url('session.info') ?>">刷新</a></p>
</div>
<?php $this->end(); ?>
