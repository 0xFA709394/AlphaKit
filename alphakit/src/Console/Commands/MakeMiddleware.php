<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成中间件: php bin/alphakit make:middleware LogRequests
 */
class MakeMiddleware extends Command
{
    protected string $signature = 'make:middleware {name : 中间件类名} {--force : 覆盖已存在文件}';
    protected string $description = '生成 HTTP 中间件';

    protected function handle(): ?int
    {
        $class = (string)$this->argument('name');

        $dir = base_path('app/Middleware');
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

namespace App\\Middleware;

use AlphaKit\\Core\\Middleware\\MiddlewareInterface;
use AlphaKit\\Core\\Request;
use AlphaKit\\Core\\Response;

/**
 * {$class} 中间件
 * 注册别名(config/app.php middleware_aliases)后在路由上使用 ->middleware('别名')
 */
final class {$class} implements MiddlewareInterface
{
    public function handle(Request \$request, callable \$next): Response
    {
        // 前置逻辑
        // ...

        \$response = \$next(\$request);

        // 后置逻辑(可修改响应)
        // \$response = \$response->withHeader('X-{$class}', '1');

        return \$response;
    }
}

PHP;
        file_put_contents($file, $template);
        $this->info("中间件已创建: app/Middleware/{$class}.php");
        return self::SUCCESS;
    }
}
