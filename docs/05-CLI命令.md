# 05 · CLI 命令

## 内置命令

`php bin/alphakit` 或 `php bin/alphakit list` 查看全部。

**开发调试**

| 命令 | 说明 |
|------|------|
| `serve [--host=] [--port=]` | 内置开发服务器(静态资源直出) |
| `routes` | 全部路由表格(METHOD / URI / HANDLER / 中间件) |
| `env` | 当前环境信息(驱动/版本/时区) |

**数据库**

| 命令 | 说明 |
|------|------|
| `migrate` | 执行未运行的迁移 |
| `migrate:rollback` | 回滚最后一批 |
| `migrate:reset` | 回滚全部 |
| `migrate:status` | 迁移状态表 |
| `db:seed` | 执行 `database/seeders.php` |

**代码生成**

| 命令 | 说明 |
|------|------|
| `make:controller Name [--api] [--resource]` | 控制器;`--api` 放 `Api/` 目录,`--resource` 生成 CRUD 五方法 |
| `make:model Name [--table=] [--migration]` | 模型;`--migration` 连带生成建表迁移 |
| `make:job Name` | 队列任务(含 tries/retryAfter 骨架) |
| `make:command Name [--cmd=name]` | 控制台命令 |
| `make:middleware Name` | 中间件 |
| `make:migration name [--table=]` | 迁移文件 |

所有 `make:*` 支持 `--force` 覆盖已存在文件。

**队列 / 调度**

| 命令 | 说明 |
|------|------|
| `queue:work [--queue=] [--once] [--max-jobs=N]` | 消费队列 |
| `queue:failed` | 失败任务列表 |
| `schedule:run` | 执行到期调度任务(系统 cron 调用) |
| `schedule:list` | 调度任务清单 |

**安全**

| 命令 | 说明 |
|------|------|
| `key:generate [--show]` | 生成 JWT_SECRET 写入 .env |

## 自定义命令

```bash
php bin/alphakit make:command SendEmails --cmd=emails:send
```

编辑生成的 `app/Commands/SendEmails.php`:

```php
final class SendEmails extends Command
{
    // 签名:{name} 必填参数 {name?} 可选 {--force} 布尔选项 {--dir=} 带值选项
    protected string $signature = 'emails:send {user : 用户 ID} {--dry-run : 只打印}';
    protected string $description = '发送待发邮件';

    protected function handle(): ?int
    {
        $userId = (int)$this->argument('user');
        $dryRun = (bool)$this->option('dry-run');

        $this->info("开始处理 #{$userId}");
        $this->table(['ID', '邮箱'], [[$userId, 'a@b.c']]);

        return self::SUCCESS;   // 0 = 成功;self::FAILURE 非 0
    }
}
```

在 `config/app.php` 的 `commands` 数组注册:

```php
'commands' => [
    \App\Commands\SendEmails::class,
],
```

运行:

```bash
php bin/alphakit emails:send 1 --dry-run
```

## 输出样式

```php
$this->info('绿色成功');       $this->error('红色错误');
$this->warn('黄色警告');       $this->line('普通输出');
$this->table($headers, $rows);  // 表格(CJK 对齐)
```
