<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Database\Migrator;

/**
 * 迁移状态: php bin/alphakit migrate:status
 */
class MigrateStatusCommand extends Command
{
    protected string $signature = 'migrate:status';
    protected string $description = '查看迁移执行状态';

    protected function handle(): ?int
    {
        /** @var Migrator $migrator */
        $migrator = app(Migrator::class);

        $rows = [];
        foreach ($migrator->status() as $name => $ran) {
            $rows[] = [$name, $ran ? 'Yes' : 'No'];
        }
        if ($rows === []) {
            $this->info('没有迁移文件');
            return self::SUCCESS;
        }
        $this->table(['Migration', 'Ran?'], $rows);
        return self::SUCCESS;
    }
}
