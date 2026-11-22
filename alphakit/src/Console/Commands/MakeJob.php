<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成队列任务: php bin/alphakit make:job SendWelcomeEmail
 */
class MakeJob extends Command
{
    protected string $signature = 'make:job {name : 任务类名} {--force : 覆盖已存在文件}';
    protected string $description = '生成队列任务类';

    protected function handle(): ?int
    {
        $class = (string)$this->argument('name');

        $dir = base_path('app/Jobs');
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

namespace App\\Jobs;

/**
 * {$class} 队列任务
 *
 * 派发: dispatch(new {$class}(\$data));
 */
final class {$class}
{
    public int \$tries = 3;        // 最大尝试次数
    public int \$retryAfter = 10;  // 重试延迟(秒)

    public function __construct(protected array \$payload = [])
    {
    }

    public function handle(): void
    {
        // TODO: 在此实现异步业务逻辑
        // logger()->info('{$class} 执行', \$this->payload);
    }

    /** 最终失败回调(可选) */
    public function failed(\\Throwable \$e): void
    {
        logger()->error('{$class} 最终失败', ['error' => \$e->getMessage()]);
    }
}

PHP;
        file_put_contents($file, $template);
        $this->info("任务已创建: app/Jobs/{$class}.php");
        return self::SUCCESS;
    }
}
