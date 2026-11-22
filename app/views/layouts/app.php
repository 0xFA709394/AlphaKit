<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->section('title', 'AlphaKit') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f5f7fa; color: #2c3e50; line-height: 1.6; }
        .nav { background: #1a2b3c; padding: 14px 24px; display: flex; align-items: center; gap: 20px; }
        .nav a { color: #cfd8e3; text-decoration: none; font-size: 14px; }
        .nav a:hover { color: #fff; }
        .brand { color: #4fc3f7 !important; font-weight: 700; font-size: 17px !important; }
        .container { max-width: 900px; margin: 32px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 10px; padding: 28px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 26px; margin-bottom: 10px; }
        h2 { font-size: 18px; margin-bottom: 12px; color: #1a2b3c; }
        code { background: #eef2f7; border-radius: 4px; padding: 2px 6px; font-size: 13px; color: #c0392b; font-family: "SF Mono", Menlo, monospace; }
        pre { background: #1a2b3c; color: #a5d6ff; padding: 14px; border-radius: 8px; overflow-x: auto; font-size: 13px; margin: 10px 0; font-family: "SF Mono", Menlo, monospace; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        th { color: #7f8c8d; font-weight: 600; }
        .tag { display: inline-block; background: #e3f2fd; color: #1565c0; border-radius: 20px; padding: 2px 12px; font-size: 12px; margin: 2px; }
        .flash { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32; padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="nav">
    <a class="brand" href="/">⚡ AlphaKit</a>
    <a href="/">首页</a>
    <a href="/docs">能力总览</a>
    <a href="/demo/session">Session 演示</a>
</div>
<div class="container">
    <?= $this->section('content') ?>
</div>
</body>
</html>
