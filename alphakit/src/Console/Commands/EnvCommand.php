<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 查看当前环境: php bin/alphakit env
 */
class EnvCommand extends Command
{
    protected string $signature = 'env';
    protected string $description = '显示当前环境信息';

    protected function handle(): ?int
    {
        $this->line('');
        $this->info('  AlphaKit v' . config('app.version', '1.0.0'));
        $this->line('');
        $rows = [
            ['应用名称', config('app.name')],
            ['环境', config('app.env')],
            ['调试模式', config('app.debug') ? '开启' : '关闭'],
            ['PHP 版本', PHP_VERSION],
            ['数据库驱动', config('database.default')],
            ['缓存驱动', config('cache.default')],
            ['队列驱动', config('queue.default')],
            ['时区', date_default_timezone_get()],
        ];
        foreach ($rows as [$k, $v]) {
            $this->line("  \033[33m" . $this->padWidth((string)$k, 12) . "\033[0m {$v}");
        }
        $this->line('');
        return self::SUCCESS;
    }
}
