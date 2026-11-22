<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成控制台命令: php bin/alphakit make:command SendEmails
 */
class MakeCommand extends Command
{
    protected string $signature = 'make:command {name : 命令类名} {--cmd= : 命令名(默认蛇形类名)}';
    protected string $description = '生成控制台命令类';

    protected function handle(): ?int
    {
        $class = (string)$this->argument('name');
        $cmd = (string)($this->option('cmd') ?: strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $class)));

        $dir = base_path('app/Commands');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = "{$dir}/{$class}.php";

        if (is_file($file) && !$this->option('force')) {
            $this->error("已存在: {$file} (使用 --force 覆盖)");
            return self::FAILURE;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Commands;

use AlphaKit\\Console\\Command;

/**
 * php bin/alphakit {$cmd}
 */
final class {$class} extends Command
{
    protected string \$signature = '{$cmd}';
    protected string \$description = 'TODO: 命令描述';

    protected function handle(): ?int
    {
        \$this->info('TODO: 实现 {$class} 业务逻辑');
        return self::SUCCESS;
    }
}

PHP;
        file_put_contents($file, $template);
        $this->info("命令已创建: app/Commands/{$class}.php");
        $this->warn("记得在 config/app.php 的 commands 数组中注册 App\\Commands\\{$class}::class");
        return self::SUCCESS;
    }
}
