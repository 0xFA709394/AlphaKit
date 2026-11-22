<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成迁移: php bin/alphakit make:migration create_users_table
 */
class MakeMigration extends Command
{
    protected string $signature = 'make:migration {name : 迁移名(蛇形)} {--table= : 表名} {--create : 建表迁移}';
    protected string $description = '生成数据库迁移文件';

    protected function handle(): ?int
    {
        $name = (string)$this->argument('name');
        $table = (string)$this->option('table', '');
        $isCreate = $this->option('create') || str_starts_with($name, 'create_') || $table !== '';

        // 类名: create_users_table -> CreateUsersTable
        $class = str_replace('_', '', ucwords($name, '_'));
        $filename = date('Ymd_His') . '_' . $name . '.php';
        $dir = database_path('migrations');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $upBody = $isCreate
            ? <<<PHP
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            // \$table->string('name');
            \$table->timestamps();
        });
PHP
            : <<<PHP
        // ALTER 示例:
        // (new \\AlphaKit\\Database\\Connection())->execute('ALTER TABLE "{$table}" ADD COLUMN "extra" TEXT');
PHP;

        $downBody = $isCreate
            ? "        Schema::dropIfExists('{$table}');"
            : '        // 逆向变更';

        $template = <<<PHP
<?php

declare(strict_types=1);

use AlphaKit\\Database\\Migration;
use AlphaKit\\Database\\Schema\\Blueprint;
use AlphaKit\\Database\\Schema\\Schema;

final class {$class} extends Migration
{
    public function up(): void
    {
{$upBody}
    }

    public function down(): void
    {
{$downBody}
    }
}

PHP;

        $file = $dir . '/' . $filename;
        file_put_contents($file, $template);
        $this->info("迁移已创建: database/migrations/{$filename}");
        return self::SUCCESS;
    }
}
