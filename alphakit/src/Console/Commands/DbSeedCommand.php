<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 数据填充: php bin/alphakit db:seed
 * 执行 database/seeders.php(存在时)
 */
class DbSeedCommand extends Command
{
    protected string $signature = 'db:seed';
    protected string $description = '执行数据库填充(database/seeders.php)';

    protected function handle(): ?int
    {
        $file = database_path('seeders.php');
        if (!is_file($file)) {
            $this->warn('未找到 database/seeders.php,跳过');
            return self::SUCCESS;
        }

        $seeder = require $file;
        if ($seeder instanceof \Closure) {
            $seeder();
        }

        $this->info('数据填充完成');
        return self::SUCCESS;
    }
}
