<?php
/** @var string $appName */
/** @var string $phpVersion */
/** @var array<int, array{0:string,1:string}> $features */

$this->extend('layouts/app');
$this->start('title'); ?><?= e($appName) ?> - 快速开发框架<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>⚡ <?= e($appName) ?> <span class="tag">PHP <?= e($phpVersion) ?></span></h1>
    <p>一套支持各类需求类型的 PHP 快速开发框架 —— API / Web / CLI / 队列 / 定时任务,开箱即用。</p>
</div>

<div class="card">
    <h2>核心能力</h2>
    <table>
        <thead><tr><th>能力</th><th>说明</th></tr></thead>
        <tbody>
        <?php foreach ($features as [$name, $desc]): ?>
            <tr><td><strong><?= e($name) ?></strong></td><td><?= e($desc) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>快速开始</h2>
    <pre># 1. 安装依赖(零依赖,可直接跳过)
php bin/alphakit serve                 # 启动开发服务器

# 2. 初始化数据库(SQLite,开箱即用)
php bin/alphakit migrate               # 建表
php bin/alphakit db:seed               # 种子数据(demo@alphakit.dev / demo1234)

# 3. 体验 API
curl http://127.0.0.1:8000/api/health
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@alphakit.dev","password":"demo1234"}'</pre>
    <p>完整文档见 <a href="/docs">能力总览</a> 与项目 <code>docs/</code> 目录。</p>
</div>
<?php $this->end(); ?>
