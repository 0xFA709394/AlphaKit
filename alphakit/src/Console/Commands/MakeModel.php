<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成模型: php bin/alphakit make:model Task [--table=tasks]
 */
class MakeModel extends Command
{
    protected string $signature = 'make:model {name : 模型类名} {--table= : 关联表名} {--migration : 同时生成迁移} {--force : 覆盖已存在文件}';
    protected string $description = '生成 Active Record 模型';

    protected function handle(): ?int
    {
        $class = (string)$this->argument('name');
        $table = (string)($this->option('table') ?: $this->guessTable($class));

        $dir = base_path('app/Models');
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

namespace App\\Models;

use AlphaKit\\Database\\Model;

/**
 * {$class} 模型
 */
final class {$class} extends Model
{
    protected string \$table = '{$table}';

    /** 序列化时隐藏 */
    protected array \$hidden = [];

    /** 类型转换 */
    protected array \$casts = [];

    // 关联示例:
    // public function user(): ?User
    // {
    //     return \$this->belongsTo(User::class);
    // }
}

PHP;
        file_put_contents($file, $template);
        $this->info("模型已创建: app/Models/{$class}.php");

        if ($this->option('migration')) {
            $this->call('make:migration', ['name' => 'create_' . $table . '_table', '--table' => $table]);
        }
        return self::SUCCESS;
    }

    protected function guessTable(string $class): string
    {
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
        return $snake . (str_ends_with($snake, 's') ? '' : 's');
    }

    /** 转调其他命令 */
    protected function call(string $command, array $args = []): int
    {
        $console = new \AlphaKit\Console\ConsoleApplication();
        $argv = array_merge(['alphakit', $command], $args);
        // 转换为原始参数序列
        $raw = [];
        foreach ($args as $k => $v) {
            if (str_starts_with((string)$k, '--')) {
                $raw[] = "{$k}={$v}";
            } else {
                $raw[] = $v;
            }
        }
        return $console->run(array_merge(['alphakit', $command], $raw));
    }
}
