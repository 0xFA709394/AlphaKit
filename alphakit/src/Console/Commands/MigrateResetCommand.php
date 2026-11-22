<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Database\Migrator;

/**
 * 重置全部: php bin/alphakit migrate:reset
 */
class MigrateResetCommand extends Command
{
    protected string $signature = 'migrate:reset';
    protected string $description = '回滚全部迁移';

    protected function handle(): ?int
    {
        /** @var Migrator $migrator */
        $migrator = app(Migrator::class);

        $rolled = $migrator->reset();
        foreach ($rolled as $name) {
            $this->warn("ROLLED BACK  {$name}");
        }
        $this->info('重置完成');
        return self::SUCCESS;
    }
}
