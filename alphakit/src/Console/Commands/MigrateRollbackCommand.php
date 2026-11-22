<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Database\Migrator;

/**
 * 回滚最后一批: php bin/alphakit migrate:rollback
 */
class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback';
    protected string $description = '回滚最后一批迁移';

    protected function handle(): ?int
    {
        /** @var Migrator $migrator */
        $migrator = app(Migrator::class);

        $rolled = $migrator->rollback();
        if ($rolled === []) {
            $this->info('没有可回滚的迁移');
            return self::SUCCESS;
        }

        foreach ($rolled as $name) {
            $this->warn("ROLLED BACK  {$name}");
        }
        return self::SUCCESS;
    }
}
