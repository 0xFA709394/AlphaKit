<?php
/** @var array<int, array{method:string,path:string,desc:string}> $routes */

$this->extend('layouts/app');
$this->start('title'); ?>能力总览 - AlphaKit<?php $this->end();
$this->start('content');
?>
<div class="card">
    <h1>能力总览</h1>
    <p>演示应用覆盖了框架的全部核心能力:</p>
    <table>
        <thead><tr><th>方法</th><th>路径 / 命令</th><th>说明</th></tr></thead>
        <tbody>
        <?php foreach ($routes as $route): ?>
            <tr>
                <td><code><?= e($route['method']) ?></code></td>
                <td><code><?= e($route['path']) ?></code></td>
                <td><?= e($route['desc']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>常用 CLI 命令</h2>
    <pre>php bin/alphakit list                  # 全部命令
php bin/alphakit make:controller PostController --api --resource
php bin/alphakit make:model Post --migration
php bin/alphakit make:job SyncData
php bin/alphakit migrate / migrate:rollback / migrate:status
php bin/alphakit queue:work --once
php bin/alphakit schedule:list
php bin/alphakit routes</pre>
</div>
<?php $this->end(); ?>
