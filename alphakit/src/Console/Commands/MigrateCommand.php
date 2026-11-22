<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Database\Migrator;

/**
 * 执行迁移: php bin/alphakit migrate
 */
class MigrateCommand extends Command
{
    protected string $signature = 'migrate';
    protected string $description = '执行未运行的数据库迁移';

    protected function handle(): ?int
    {
        /** @var Migrator $migrator */
        $migrator = app(Migrator::class);

        $executed = $migrator->migrate();

        if ($executed === []) {
            $this->info('没有待执行的迁移');
            return self::SUCCESS;
        }

        foreach ($executed as $name) {
            $this->info("MIGRATED  {$name}");
        }
        $this->line('');
        $this->info('完成 ' . count($executed) . ' 个迁移');
        return self::SUCCESS;
    }
}
