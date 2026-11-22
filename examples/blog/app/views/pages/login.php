<?php
$this->extend('layouts/blog');
$this->start('title'); ?>登录 - AlphaBlog<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>登录(演示)</h1>
    <p class="muted">输入任意昵称即可"登录":演示校验 + Session regenerate + flash + 重定向。</p>
    <form method="post" action="<?= \url('login.submit') ?>" style="margin-top:10px">
        <input name="name" placeholder="昵称" required
               style="padding:8px;border:1px solid #ccc;border-radius:6px;width:220px">
        <button>进入</button>
    </form>
</div>
<?php $this->end(); ?>
