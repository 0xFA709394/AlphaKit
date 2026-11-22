<?php

declare(strict_types=1);

/**
 * 数据填充:php bin/alphakit db:seed
 * 返回闭包,由 db:seed 命令执行
 */

use App\Models\Task;
use App\Models\User;
use AlphaKit\Database\Connection;

return function (): void {
    // 演示账号(密码: demo1234)
    $demoUser = User::query()->where('email', '=', 'demo@alphakit.dev')->first();

    if ($demoUser === null) {
        $demoUser = User::create([
            'name' => '演示用户',
            'email' => 'demo@alphakit.dev',
            'password' => password_hash('demo1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        echo "已创建演示用户: demo@alphakit.dev / demo1234\n";
    }

    if (Task::count() === 0) {
        Task::create([
            'user_id' => $demoUser->id,
            'title' => '体验 AlphaKit 任务管理',
            'description' => '这是一个种子任务,试试 POST /api/tasks 创建更多',
            'done' => 0,
            'tags' => json_encode(['demo', 'seed'], JSON_UNESCAPED_UNICODE),
        ]);
        echo "已创建 1 条演示任务\n";
    }

    // 队列表(数据库队列驱动使用,幂等)
    $connection = app(Connection::class);
    if (!$connection->tableExists('jobs')) {
        app(\AlphaKit\Queue\QueueManager::class)->size(); // 触发自动建表
    }
};
