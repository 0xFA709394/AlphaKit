<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 启动开发服务器: php bin/alphakit serve [--host=0.0.0.0] [--port=8000]
 */
class ServeCommand extends Command
{
    protected string $signature = 'serve {--host= : 监听地址} {--port= : 监听端口}';
    protected string $description = '启动 PHP 内置开发服务器';

    protected function handle(): ?int
    {
        $host = (string)$this->option('host', '127.0.0.1');
        $port = (string)$this->option('port', '8000');
        $router = base_path('alphakit/server.php');

        $this->info("AlphaKit 开发服务器已启动: http://{$host}:{$port}");
        $this->line("按 Ctrl+C 停止\n");

        passthru(sprintf(
            '%s -S %s:%s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg("{$host}:{$port}"),
            escapeshellarg($router)
        ));
        return self::SUCCESS;
    }
}
