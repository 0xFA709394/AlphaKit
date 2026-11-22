<?php

declare(strict_types=1);

namespace App\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Database\QueryBuilder;

/**
 * php bin/console tags:sync —— upsert 批量同步演示(make:command 生成后填充)
 */
final class TagsSyncCommand extends Command
{
    protected string $signature = 'tags:sync {--weights= : 逗号分隔的 名称:权重 列表}';
    protected string $description = '同步预设标签(upsert 演示)';

    protected function handle(): ?int
    {
        $defaults = ['php:90', 'framework:80', 'alphakit:100', 'mysql:70', 'queue:60'];
        $raw = $this->option('weights');
        if (is_string($raw) && $raw !== '') {
            $defaults = array_map('trim', explode(',', $raw));
        }

        $synced = 0;
        foreach ($defaults as $item) {
            [$name, $weight] = array_pad(explode(':', $item), 2, '50');
            QueryBuilder::table('tags')->upsert(
                ['name' => $name, 'weight' => (int)$weight, 'created_at' => now(), 'updated_at' => now()],
                ['name']
            );
            $synced++;
        }

        $this->info("同步完成:{$synced} 个标签(upsert 幂等,可重复执行)");
        return self::SUCCESS;
    }
}
