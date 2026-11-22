<?php

declare(strict_types=1);

/**
 * MySQL 方言真跑测试(全部核心路径在真实 MySQL/MariaDB 上执行)
 *
 * 用法:先建测试库(如 alphakit_test),然后:
 *   AK_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=alphakit_test;charset=utf8mb4' \
 *   AK_MYSQL_USER=root AK_MYSQL_PASS=secret php tests/mysql_test.php
 *
 * 覆盖:wrap 反引号 / 真实迁移 DDL / Model CRUD 与 casts / upsert /
 * 队列表自动建表+消费 / 失败表 / 分页聚合 join / 嵌套事务 / 回滚全链路
 */

require __DIR__ . '/../alphakit/bootstrap.php';

use AlphaKit\Database\Connection;
use AlphaKit\Database\Migrator;
use AlphaKit\Database\QueryBuilder;
use AlphaKit\Queue\QueueManager;

error_reporting(E_ALL & ~E_DEPRECATED);

$dsn = getenv('AK_MYSQL_DSN') ?: '';
if ($dsn === '') {
    exit("缺少 AK_MYSQL_DSN\n");
}

// 覆盖容器连接为 MySQL(测试期间的全局连接)
app()->instance(Connection::class, new Connection([
    'driver' => 'mysql',
    'dsn' => $dsn,
    'username' => getenv('AK_MYSQL_USER') ?: 'root',
    'password' => getenv('AK_MYSQL_PASS') ?: '',
]));

$conn = app(Connection::class);

$passed = 0;
$failed = 0;
function t(string $name, bool $ok): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        echo "  ✗ {$name}\n";
    }
}

function throws(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable $e) {
        return true;
    }
}

echo "── 1. wrap 反引号方言 ──\n";
t('wrap 返回反引号', $conn->wrap('users') === '`users`');
t('点号限定名不转义', $conn->wrap('users.id') === 'users.id');
t('表存在检测(information_schema)', $conn->tableExists('users') === false);

echo "── 2. 真实迁移(MySQL DDL 全链路) ──\n";
$mig = new Migrator($conn);
$executed = $mig->migrate();
t('两个迁移执行成功', count($executed) === 2);
t('users 表已建', $conn->tableExists('users'));
t('tasks 表已建', $conn->tableExists('tasks'));
t('migrations 表已建(方言列名)', $conn->tableExists('migrations'));

echo "── 3. Model CRUD(反引号 INSERT/SELECT/UPDATE) ──\n";
$user = App\Models\User::create(['name' => 'mysql用户', 'email' => 'mysql@test.dev', 'password' => password_hash('pw123456', PASSWORD_DEFAULT), 'is_active' => 1]);
t('INSERT 返回自增 ID', $user->id > 0);
$found = App\Models\User::find($user->id);
t('SELECT 反引号查询', $found !== null && $found->email === 'mysql@test.dev');
$found->update(['name' => 'mysql改名']);
t('UPDATE 反引号', App\Models\User::find($user->id)->name === 'mysql改名');
t('Model casts(is_active bool)', App\Models\User::find($user->id)->is_active === true);

$task = App\Models\Task::create(['user_id' => $user->id, 'title' => 'mysql任务', 'tags' => json_encode(['m', 'db'], JSON_UNESCAPED_UNICODE)]);
$back = App\Models\Task::find($task->id);
t('tags json cast', $back->tags === ['m', 'db']);
$back->update(['done' => 1]);
t('boolean update + cast', App\Models\Task::find($task->id)->done === true);

echo "── 4. upsert ON DUPLICATE KEY UPDATE ──\n";
QueryBuilder::table('tasks')->upsert(['id' => 777, 'user_id' => $user->id, 'title' => 'up1', 'done' => 0, 'created_at' => now(), 'updated_at' => now()], ['id']);
QueryBuilder::table('tasks')->upsert(['id' => 777, 'user_id' => $user->id, 'title' => 'up2', 'done' => 0, 'created_at' => now(), 'updated_at' => now()], ['id']);
t('upsert 更新生效', QueryBuilder::table('tasks')->where('id', 777)->value('title') === 'up2');
QueryBuilder::table('tasks')->where('id', 777)->delete();

echo "── 5. 队列自动建表(MySQL 语法) ──\n";
final class MysqlFailingJob
{
    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('boom');
    }
}

$qm = new QueueManager($conn);
$qm->push(new App\Jobs\CleanDoneTasks(), 'mysql-q');
t('jobs 表自动创建并成功入队', $conn->tableExists('jobs') && $qm->size('mysql-q') === 1);
$qm->later(60, new App\Jobs\CleanDoneTasks(), 'mysql-q');
t('later 入队 MySQL 列名写入', $qm->size('mysql-q') === 2);
(new AlphaKit\Queue\Worker($qm, logger('mysql-q')))->run('mysql-q', daemon: false, maxJobs: 10);
t('worker 只消费到期任务(延迟任务保留)', $qm->size('mysql-q') === 1);
$conn->execute('DELETE FROM `jobs` WHERE `queue` = ?', ['mysql-q']);

// 失败任务触发 failed_jobs 自动建表
$qm->push(new MysqlFailingJob(), 'mysql-q');
(new AlphaKit\Queue\Worker($qm, logger('mysql-q')))->run('mysql-q', daemon: false, maxJobs: 10);
t('失败任务记入 failed_jobs(自动建表)', $conn->tableExists('failed_jobs')
    && (int)$conn->scalar('SELECT COUNT(*) FROM `failed_jobs`') === 1);
$conn->execute('DELETE FROM `failed_jobs`');

echo "── 6. 分页/聚合/join ──\n";
$pg = App\Models\Task::query()->where('user_id', '=', $user->id)->orderBy('id', 'desc')->paginate(1, 1);
t('MySQL 分页 LIMIT/OFFSET', $pg['total'] === 1 && count($pg['data']) === 1);
t('MySQL 聚合剥离 order/offset', App\Models\Task::query()->orderBy('id', 'desc')->offset(1)->count() >= 1);
$joined = QueryBuilder::table('tasks')->join('users', 'users.id', '=', 'tasks.user_id')->select('tasks.title', 'users.email')->limit(2)->get();
t('join 反引号/点号透传', isset($joined[0]['title'], $joined[0]['email']));

echo "── 7. 事务 + 嵌套 SAVEPOINT ──\n";
$conn->transaction(function () use ($conn, $task) {
    $conn->execute('UPDATE `tasks` SET `done` = 0 WHERE `id` = ?', [$task->id]);
    try {
        $conn->transaction(function () use ($conn, $task) {
            $conn->execute('UPDATE `tasks` SET `done` = 1 WHERE `id` = ?', [$task->id]);
            throw new RuntimeException('inner');
        });
    } catch (RuntimeException) {
    }
});
t('嵌套事务保存点回滚', (int)QueryBuilder::table('tasks')->where('id', $task->id)->value('done') === 0);

echo "── 8. 回滚全链路(RENAME 不涉及,DROP IF EXISTS) ──\n";
$rolled = $mig->rollback();
t('回滚最后一批', count($rolled) === 2 && !$conn->tableExists('tasks') && !$conn->tableExists('users'));

// 清理测试数据
$conn->execute('DROP TABLE IF EXISTS `tasks`');
$conn->execute('DROP TABLE IF EXISTS `users`');
$conn->execute('DELETE FROM `migrations`');

echo "\n════════════════════════════\n  MySQL 方言: 通过 {$passed}  失败 {$failed}\n";
exit($failed === 0 ? 0 : 1);
