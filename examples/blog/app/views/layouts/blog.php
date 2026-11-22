<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->section('title', 'AlphaBlog') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f6f8fa; color: #24292f; line-height: 1.7; }
        .nav { background: #0d2137; padding: 14px 24px; display: flex; gap: 18px; align-items: center; }
        .nav a { color: #b9cbe0; text-decoration: none; font-size: 14px; }
        .nav a:hover { color: #fff; }
        .brand { color: #58c4ff !important; font-weight: 700; font-size: 17px !important; }
        .container { max-width: 860px; margin: 28px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 10px; padding: 24px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.09); }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .muted { color: #7a869a; font-size: 13px; }
        code { background: #eef2f7; border-radius: 4px; padding: 2px 6px; font-size: 13px; color: #b3502d; }
        .flash { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; }
        .tag { display: inline-block; background: #e3f2fd; color: #1565c0; border-radius: 20px; padding: 2px 12px; font-size: 12px; margin: 2px; }
        form.inline { display: inline; }
        button { background: #0d2137; color: #fff; border: 0; border-radius: 6px; padding: 6px 16px; cursor: pointer; font-size: 13px; }
    </style>
</head>
<body>
<div class="nav">
    <a class="brand" href="<?= \url('home') ?>">📝 AlphaBlog</a>
    <a href="<?= \url('home') ?>">首页</a>
    <a href="<?= \url('session.info') ?>">Session 演示</a>
    <a href="<?= \url('login.form') ?>">登录</a>
</div>
<div class="container">
    <?= $this->insert('partials/flash') ?>
    <?= $this->section('content') ?>
</div>
</body>
</html>
