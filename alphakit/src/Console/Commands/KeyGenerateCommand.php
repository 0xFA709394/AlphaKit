<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成 JWT 密钥并写入 .env: php bin/alphakit key:generate [--show]
 */
class KeyGenerateCommand extends Command
{
    protected string $signature = 'key:generate {--show : 仅打印不写入}';
    protected string $description = '生成应用密钥(JWT_SECRET)写入 .env';

    protected function handle(): ?int
    {
        $key = bin2hex(random_bytes(32));

        if ($this->option('show')) {
            $this->line($key);
            return self::SUCCESS;
        }

        $envFile = base_path('.env');
        if (!is_file($envFile)) {
            if (is_file(base_path('.env.example'))) {
                copy(base_path('.env.example'), $envFile);
            } else {
                file_put_contents($envFile, '');
            }
        }

        $content = (string)file_get_contents($envFile);
        if (preg_match('/^JWT_SECRET=.*$/m', $content)) {
            $content = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $key, $content);
        } else {
            $content = rtrim($content) . "\nJWT_SECRET={$key}\n";
        }
        file_put_contents($envFile, $content);

        $this->info('JWT_SECRET 已生成并写入 .env');
        return self::SUCCESS;
    }
}
