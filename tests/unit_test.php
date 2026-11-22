<?php

declare(strict_types=1);

/**
 * AlphaKit 单元回归测试
 * 覆盖代码审查发现的全部边界情况,防止回归。
 *
 * 运行: php tests/unit_test.php   (断言失败以非零码退出)
 */

require dirname(__DIR__) . '/alphakit/bootstrap.php';

use AlphaKit\Auth\JWT;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Core\Route;
use AlphaKit\Core\Router;
use AlphaKit\Core\Session;
use AlphaKit\Database\Connection;
use AlphaKit\Database\QueryBuilder;
use AlphaKit\Database\Schema\Blueprint;
use AlphaKit\Schedule\CronExpression;
use AlphaKit\Validation\Validator;

error_reporting(E_ALL & ~E_DEPRECATED);

$passed = 0;
$failed = 0;

function t(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        echo "  ✗ {$name}\n";
    }
}

function throws(callable $fn, string $needle = ''): bool
{
    try {
        $fn();
        return false;
    } catch (Throwable $e) {
        return $needle === '' || str_contains($e->getMessage(), $needle);
    }
}

echo "── 1. 路由 ──\n";
// 可选参数:裸路径 / 带值 / 约束+可选混合 / 不匹配
$r = new Route('GET', '/news/{page?}', fn () => null, []);
t('可选参数匹配裸路径', $r->matches('/news') === []);
t('可选参数匹配带值', ($r->matches('/news/3') ?? [])['page'] === '3');
$r2 = new Route('GET', '/docs/{id:\d+}/{page?}', fn () => null, []);
t('约束+可选混合(裸)', ($r2->matches('/docs/7') ?? [])['id'] === '7');
t('约束+可选混合(全参)', ($r2->matches('/docs/7/2') ?? [])['page'] === '2');
t('约束不匹配返回 null', $r->matches('/docs/abc') === null && $r2->matches('/docs/abc') === null);
t('必填参数正常', ((new Route('GET', '/u/{id:\d+}', fn () => null, []))->matches('/u/42') ?? [])['id'] === '42');

// 命名路由与 url 生成(独立 Router 实例,验证不依赖容器)
$router = new Router();
$router->get('/news/{page?}', fn () => null)->name('u-news');
t('url() 可选参数缺省时整段移除', $router->url('u-news') === '/news');
t('url() 可选参数提供时替换', $router->url('u-news', ['page' => 9]) === '/news/9');
t('url() 未定义路由抛异常', throws(fn () => $router->url('nope'), '未定义'));

// 405 检测
$router->post('/only-post', fn () => null);
t('方法不允许抛 405', throws(fn () => $router->match('GET', '/only-post'), 'not allowed'));

echo "── 2. 查询构造器 ──\n";
t('whereIn 空集恒假', QueryBuilder::table('tasks')->whereIn('id', [])->count() === 0);
t('whereNotIn 空集恒真', QueryBuilder::table('tasks')->whereNotIn('id', [])->count() > 0);
t('count 剥离 orderBy/offset 不再报错', QueryBuilder::table('tasks')->orderBy('id', 'desc')->offset(5)->count() >= 0);
t('分页 last_page 下限为 1', QueryBuilder::table('tasks')->where('id', '=', -1)->paginate(15)['last_page'] === 1);
t('无 WHERE 的全表 UPDATE 被拒绝', throws(fn () => QueryBuilder::table('tasks')->update(['done' => 1]), '全表'));
t('无 WHERE 的全表 DELETE 被拒绝', throws(fn () => QueryBuilder::table('tasks')->delete(), '全表'));
t('嵌套 OR 条件组 SQL 正确', QueryBuilder::table('tasks')->where('a', 1)->whereGroup(fn ($q) => $q->where('b', 2)->orWhere('c', 3))->toSql()[0]
    === 'SELECT * FROM "tasks" WHERE "a" = ? AND ("b" = ? OR "c" = ?)');
t('whereIn 正常集合', QueryBuilder::table('tasks')->whereIn('id', [1, 2, 3])->toSql()[0] === 'SELECT * FROM "tasks" WHERE "id" IN (?, ?, ?)');

echo "── 3. upsert / 事务 / Model ──\n";
QueryBuilder::table('tasks')->upsert(
    ['id' => 987001, 'user_id' => 1, 'title' => 'upsert-a', 'done' => 0, 'created_at' => now(), 'updated_at' => now()],
    ['id']
);
QueryBuilder::table('tasks')->upsert(
    ['id' => 987001, 'user_id' => 1, 'title' => 'upsert-b', 'done' => 0, 'created_at' => now(), 'updated_at' => now()],
    ['id']
);
t('upsert 插入后更新', QueryBuilder::table('tasks')->where('id', 987001)->value('title') === 'upsert-b');
QueryBuilder::table('tasks')->where('id', 987001)->delete();

// 嵌套事务:内层回滚到保存点,外层照常提交
$conn = app(Connection::class);
$conn->transaction(function () use ($conn) {
    $conn->execute('UPDATE tasks SET done = 0 WHERE id = 1');
    try {
        $conn->transaction(function () use ($conn) {
            $conn->execute('UPDATE tasks SET done = 1 WHERE id = 1');
            throw new RuntimeException('inner');
        });
    } catch (RuntimeException) {
    }
});
t('嵌套事务回滚到保存点', (int)QueryBuilder::table('tasks')->where('id', 1)->value('done') === 0);

// Model 脏检测(严格比较:类型变化也算脏)
$task = App\Models\Task::find(1);
$task->done = 1; // int,数据库读出为 string "0"
$task->save();
t('Model 脏检测严格比较生效', (int)App\Models\Task::find(1)->done === 1);
t('findOrFail 不存在抛 404', throws(fn () => App\Models\Task::findOrFail(999999), '不存在'));
t('hasMany 关联', count(App\Models\User::find(1)->tasks()) >= 1);
t('belongsTo 关联', (App\Models\Task::find(1)->user()->email ?? '') === 'demo@alphakit.dev');
t('hidden 字段不外泄', !array_key_exists('password', App\Models\User::find(1)->toArray()));

echo "── 4. 容器 / 中间件 ──\n";
t('循环依赖被检测', throws(function () {
    $c = new AlphaKit\Core\Container();
    $c->bind('X1', fn () => new class { });
    // 构造真实循环
    eval('class CycP { public function __construct(CycQ $q) {} } class CycQ { public function __construct(CycP $p) {} }');
    (new AlphaKit\Core\Container())->make('CycP');
}, '循环依赖'));

$cast = new ReflectionMethod(AlphaKit\Core\Middleware\Pipeline::class, 'castParameters');
$pipe = app(AlphaKit\Core\Middleware\Pipeline::class);
$throttle = new App\Middleware\ThrottleRequests();
t("中间件参数 '060' 转 int", $cast->invoke($pipe, [$throttle, 'handle'], ['060'])[0] === 60);
t("中间件参数 '-5' 转 int", $cast->invoke($pipe, [$throttle, 'handle'], ['-5'])[0] === -5);
t("中间件参数 '60x' 保持 string", $cast->invoke($pipe, [$throttle, 'handle'], ['60x'])[0] === '60x');

echo "── 5. Schema / Migrator ──\n";
$b = new Blueprint();
$b->unique(); // 空列安全
$b->id();
$b->string('email')->unique();
$b->boolean('active')->default(1);
$b->timestamps();
$sql = $b->toSql('t_demo');
t('Blueprint 编译含唯一约束', str_contains($sql, 'UNIQUE'));
t('Blueprint 编译含默认值', str_contains($sql, 'DEFAULT 1'));
t('Blueprint 索引独立生成', $b->index('email') && str_contains($b->toIndexSql('t_demo')[0], 'CREATE INDEX'));

// 迁移类名冲突检测:两个文件声明同名迁移类 → 拦截为清晰错误(而非 PHP fatal redeclare)
$tmpDir = database_path('migrations');
file_put_contents($tmpDir . '/9999_01_01_000001_unit_dup_table.php', '<?php use AlphaKit\Database\Migration; final class UnitDupTable extends Migration { public function up(): void {} public function down(): void {} }');
file_put_contents($tmpDir . '/9999_01_01_000002_unit_dup_table.php', '<?php use AlphaKit\Database\Migration; final class UnitDupTable extends Migration { public function up(): void {} public function down(): void {} }');
try {
    t('迁移类名冲突被拦截', throws(fn () => app(AlphaKit\Database\Migrator::class)->migrate(), '迁移类名冲突'));
} finally {
    @unlink($tmpDir . '/9999_01_01_000001_unit_dup_table.php');
    @unlink($tmpDir . '/9999_01_01_000002_unit_dup_table.php');
    QueryBuilder::table('migrations')->where('migration', 'like', '9999_%')->delete();
}

echo "── 6. Cron / JWT / 缓存 ──\n";
$cron = new CronExpression('*/5 * * * *');
t('cron */5 命中', $cron->isDue(strtotime('2026-11-22 13:05:00')));
t('cron */5 未命中', !$cron->isDue(strtotime('2026-11-22 13:06:00')));
t('cron 周范围 1-5 命中周一', (new CronExpression('0 9 * * 1-5'))->isDue(strtotime('2026-11-23 09:00:00')));
t('cron 周范围 1-5 不含周日', !(new CronExpression('0 9 * * 1-5'))->isDue(strtotime('2026-11-22 09:00:00')));
t('cron 起点步长 5/15', (new CronExpression('5/15 * * * *'))->isDue(strtotime('2026-11-22 13:35:00'))
    && !(new CronExpression('5/15 * * * *'))->isDue(strtotime('2026-11-22 13:15:00')));
t('cron 非法表达式抛异常', throws(fn () => (new CronExpression('bad expr'))->isDue(time()), '非法'));

$token = JWT::encode(['sub' => 42], 'secret-x', 'HS256', 60);
t('JWT 正常解码', JWT::decode($token, 'secret-x')['sub'] === 42);
t('JWT 错误密钥被拒', throws(fn () => JWT::decode($token, 'wrong'), '签名'));
$expired = JWT::encode(['sub' => 1], 'secret-x', 'HS256', -1);
t('JWT 过期被拒', throws(fn () => JWT::decode($expired, 'secret-x'), '过期'));
t('JWT 格式错误被拒', throws(fn () => JWT::decode('a.b', 'secret-x'), '格式'));

$cache = new AlphaKit\Cache\FileStore(['path' => storage_path('cache/unit-test')]);
$cache->clear();
$cache->set('k', ['v' => 1], 60);
t('缓存读写', $cache->get('k') === ['v' => 1]);
t('缓存过期失效', ($cache->set('e', 1, -1) && $cache->get('e') === null));
t('缓存 getOrSet', $cache->getOrSet('c', 60, fn () => 'computed') === 'computed');
t('缓存 increment', $cache->increment('n') === 1 && $cache->increment('n') === 2);
t('缓存 pull 读取即删', $cache->pull('k') === ['v' => 1] && $cache->get('k') === null);

echo "── 7. 验证器 / Session / env ──\n";
$v = new Validator(['email' => 'demo@alphakit.dev'], ['email' => 'unique:users,email,1']); // 排除 id=1(自身)
t('unique 排除自身', $v->passes());
$v2 = new Validator(['email' => 'demo@alphakit.dev'], ['email' => 'unique:users,email']);
t('unique 不排除则冲突', $v2->fails());
$v3 = new Validator(['a' => ['b' => 'ok@x.dev']], ['a.b' => 'email']);
t('点号嵌套校验', $v3->passes());
$v4 = new Validator(['phone' => '13800138000'], ['phone' => 'mobile']);
t('手机号规则', $v4->passes());

$dir = storage_path('cache/sessions-unit');
$s1 = new Session($dir);
$s1->start(new Request('GET', '/', [], [], []));
$sid = $s1->id();
$s1->flash('msg', 'hello');
$s1->put('cnt', 1);
$s1->save(new Response(''));
$_COOKIE['alphakit_session'] = $sid;
$s2 = new Session($dir);
$s2->start(new Request('GET', '/', [], [], []));
t('flash 下一请求可见', $s2->getFlash('msg') === 'hello');
t('session 数据跨请求', $s2->get('cnt') === 1);
$s2->put('cnt', 2);
$s2->save(new Response(''));
$s3 = new Session($dir);
$s3->start(new Request('GET', '/', [], [], []));
t('flash 第三请求消失', $s3->getFlash('msg') === null);
t('session 更新已持久化', $s3->get('cnt') === 2);
unset($_COOKIE['alphakit_session']);

t('env 布尔语义', env('APP_DEBUG', false) === true);
t('config 点号默认值', config('not.exists.key', 'dft') === 'dft');

echo "── 8. Request / Response ──\n";
$req = new Request('POST', '/x?from=cli', ['from' => 'cli'], ['title' => 't'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/x?from=cli', 'HTTP_AUTHORIZATION' => 'Bearer abc', 'HTTP_ACCEPT' => 'application/json']);
t('query 解析', $req->input('from') === 'cli');
t('body 优先于 query', $req->input('title') === 't');
t('bearerToken 解析', $req->bearerToken() === 'abc');
t('expectsJson 检测', $req->expectsJson());
t('json 响应统一结构', str_contains(Response::ok(['a' => 1])->body(), '"code":0'));
t('404 abort 抛 HttpException', throws(fn () => abort(404, 'x'), 'x'));

echo "── 9. 第二轮审查回归 ──\n";
// Dotenv:引号内的 # 不是注释
$envFile = tempnam(sys_get_temp_dir(), 'env');
file_put_contents($envFile, "QV=\"x #1\"\nPV=y #2\n");
$_ENV = [];
putenv('QV');
putenv('PV');
AlphaKit\Core\Dotenv::load($envFile);
t('Dotenv 引号值保留 #', env('QV') === 'x #1');
t('Dotenv 裸值剥离行内注释', env('PV') === 'y');
@unlink($envFile);

// 空条件组安全跳过
t('空 whereGroup 被跳过', QueryBuilder::table('tasks')->where('user_id', 1)->whereGroup(fn ($q) => $q)->toSql()[0]
    === 'SELECT * FROM "tasks" WHERE "user_id" = ?');

// 非法 UTF-8 不再静默产生空 JSON 体
$badJson = Response::json(['bad' => "\xB1\x31"]);
t('非法 UTF-8 JSON 仍有响应体', $badJson->body() !== '' && str_contains($badJson->body(), 'bad'));
t('非法 UTF-8 JSON 含替换符', str_contains($badJson->body(), "\u{FFFD}"));

echo "── 10. 第三轮审查回归 ──\n";
// E_DEPRECATED 不再升级为异常(PHP 8.5 下第三方代码大量触发)
t('deprecation 不抛异常', (function () {
    try {
        @trigger_error('dep probe', E_USER_DEPRECATED);
        return true;
    } catch (Throwable) {
        return false;
    }
})());

// Worker 遇到类缺失的历史任务:删除+计数,进程不崩
$conn = app(Connection::class);
$conn->execute('DELETE FROM jobs WHERE queue = ?', ['unit3']);
$conn->insert('jobs', ['queue' => 'unit3', 'payload' => 'O:9:"GhostCls":1:{s:1:"x";s:1:"y";}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]);
$unitWorker = new AlphaKit\Queue\Worker(app(AlphaKit\Queue\QueueManager::class), logger('unit3'));
$unitWorker->run('unit3', daemon: false);
$ghostStats = $unitWorker->stats();
t('类缺失任务不崩 worker 且计入失败', $ghostStats['failed'] === 1 && $ghostStats['processed'] === 0);
t('类缺失任务已从队列删除', (int)$conn->scalar('SELECT COUNT(*) FROM jobs WHERE queue = ?', ['unit3']) === 0);

// Migrator 类名推导兼容两种日期前缀格式(生成器 Ymd_His 与内置 Y_m_d_NNNNNN)
$resolve = new ReflectionMethod(AlphaKit\Database\Migrator::class, 'resolve');
$mig = app(AlphaKit\Database\Migrator::class);
$migFile = database_path('migrations/9999_01_01_000001_unit_prefix_a.php');
file_put_contents($migFile, '<?php use AlphaKit\Database\Migration; final class UnitPrefixA extends Migration { public function up(): void {} }');
$t1 = $resolve->invoke($mig, $migFile, '9999_01_01_000001_unit_prefix_a') instanceof AlphaKit\Database\Migration;
@unlink($migFile);
$migFile2 = database_path('migrations/20261122_140102_unit_prefix_b.php');
file_put_contents($migFile2, '<?php use AlphaKit\Database\Migration; final class UnitPrefixB extends Migration { public function up(): void {} }');
$t2 = $resolve->invoke($mig, $migFile2, '20261122_140102_unit_prefix_b') instanceof AlphaKit\Database\Migration;
@unlink($migFile2);
t('迁移前缀推导:六段数字格式', $t1);
t('迁移前缀推导:Ymd_His 格式(生成器)', $t2);

// Session GC:过期会话文件被清理,新鲜文件保留
$gcDir = storage_path('cache/sessions-gc');
@mkdir($gcDir, 0755, true);
$oldFile = $gcDir . '/sess_' . sha1('old');
$newFile = $gcDir . '/sess_' . sha1('new');
file_put_contents($oldFile, 'a:0:{}');
touch($oldFile, time() - 7200 - 600);
file_put_contents($newFile, 'a:0:{}');
$gc = new ReflectionMethod(Session::class, 'gc');
$gc->invoke(new Session($gcDir));
t('Session GC 清理过期文件', !file_exists($oldFile));
t('Session GC 保留新鲜文件', file_exists($newFile));
@unlink($newFile);

// make:* 生成器 --force 已声明(签名可解析)
$sig = new ReflectionProperty(AlphaKit\Console\Commands\MakeJob::class, 'signature');
t('make:job 签名含 --force 声明', str_contains((string)$sig->getValue(new AlphaKit\Console\Commands\MakeJob()), '{--force'));

echo "── 11. 第五轮审查回归 ──\n";
// 事件系统四种监听器形态
$events = new AlphaKit\Core\Event\EventDispatcher();
$ev = new stdClass();
$ev->n = 9;
$events->on(stdClass::class, fn ($e) => 'closure:' . $e->n);
$listenerObj = new class {
    public function go($e)
    {
        return 'obj:' . $e->n;
    }
};
$events->on(stdClass::class, [$listenerObj, 'go']);
t('事件:闭包+对象方法监听', $events->dispatch($ev) === ['closure:9', 'obj:9']);

// join / increment / chunk 真跑
$joined = QueryBuilder::table('tasks')->join('users', 'users.id', '=', 'tasks.user_id')->select('tasks.title', 'users.email')->limit(2)->get();
t('join 真跑并返回两表列', isset($joined[0]['title'], $joined[0]['email']));

QueryBuilder::table('tasks')->where('id', 1)->increment('done', 4);
$incVal = (int)QueryBuilder::table('tasks')->where('id', 1)->value('done');
QueryBuilder::table('tasks')->where('id', 1)->decrement('done', 4);
$decVal = (int)QueryBuilder::table('tasks')->where('id', 1)->value('done');
t('increment/decrement 原子更新', $incVal === $decVal + 4);

QueryBuilder::table('tasks')->where('user_id', 556)->delete();
for ($i = 1; $i <= 7; $i++) {
    QueryBuilder::table('tasks')->insert(['user_id' => 556, 'title' => "u5-$i", 'done' => 0, 'created_at' => now(), 'updated_at' => now()]);
}
$seen = [];
QueryBuilder::table('tasks')->where('user_id', 556)->orderBy('id')->chunk(3, function ($rows) use (&$seen) {
    foreach ($rows as $r) {
        $seen[] = $r['id'];
    }
    return true;
});
QueryBuilder::table('tasks')->where('user_id', 556)->delete();
t('chunk 分批全覆盖无重复', count($seen) === 7 && count($seen) === count(array_unique($seen)));

// download 缺失文件抛 404
t('download 缺失文件抛 NotFoundException', throws(fn () => Response::download('/nonexistent/x.bin'), '文件不存在'));

// Session regenerate:换 ID 保数据
$regDir = storage_path('cache/sessions-unit');
$sReg = new Session($regDir);
$sReg->start(new Request('GET', '/', [], [], []));
$sReg->put('rk', 'rv');
$oldSid = $sReg->id();
$sReg->save(new Response(''));
$sReg->regenerate();
t('regenerate 换新 ID', $sReg->id() !== $oldSid);
t('regenerate 保留数据', $sReg->get('rk') === 'rv');

// Config 运行时写入
app(AlphaKit\Core\Config::class)->set('runtime.unit.probe', 'v5');
t('Config set 运行时生效', config('runtime.unit.probe') === 'v5');

echo "\n════════════════════════════\n";
echo "  通过: {$passed}   失败: {$failed}\n";
exit($failed === 0 ? 0 : 1);
