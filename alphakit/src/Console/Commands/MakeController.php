<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;

/**
 * 生成控制器: php bin/alphakit make:controller UserController
 */
class MakeController extends Command
{
    protected string $signature = 'make:controller {name : 控制器类名} {--api : 生成 API 风格} {--resource : 生成资源控制器}';
    protected string $description = '生成控制器类文件';

    protected function handle(): ?int
    {
        $name = $this->argument('name');
        $api = (bool)$this->option('api');
        $resource = $api || (bool)$this->option('resource');

        [$namespace, $class] = $this->split($name, $api ? 'Controllers\\Api' : 'Controllers');
        $dir = base_path('app/' . str_replace('\\', '/', $namespace));
        $file = "{$dir}/{$class}.php";

        if (is_file($file) && !$this->option('force')) {
            $this->error("已存在: {$file} (使用 --force 覆盖)");
            return self::FAILURE;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $template = $resource ? $this->resourceTemplate($namespace, $class, $api) : $this->plainTemplate($namespace, $class);
        file_put_contents($file, $template);
        $this->info("控制器已创建: app/" . str_replace('\\', '/', $namespace) . "/{$class}.php");
        return self::SUCCESS;
    }

    protected function split(string $name, string $baseNamespace): array
    {
        $name = str_replace('/', '\\', $name);
        if (!str_contains($name, '\\')) {
            return [$baseNamespace, $name];
        }
        $namespace = substr($name, 0, (int)strrpos($name, '\\'));
        $class = substr($name, (int)strrpos($name, '\\') + 1);
        if (!str_starts_with($namespace, 'Controllers')) {
            $namespace = 'Controllers\\' . ltrim($namespace, '\\');
        }
        return [$namespace, $class];
    }

    protected function plainTemplate(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\{$namespace};

use AlphaKit\\Core\\Controller;
use AlphaKit\\Core\\Request;
use AlphaKit\\Core\\Response;

final class {$class} extends Controller
{
    public function index(Request \$request): Response
    {
        return \$this->ok(['message' => '{$class} 就绪']);
    }
}

PHP;
    }

    protected function resourceTemplate(string $namespace, string $class, bool $api): string
    {
        $stub = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\{NAMESPACE};

use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * {CLASS} 资源控制器
 * 路由注册: $router->resource('{BASE}', {CLASS}::class);
 */
final class {CLASS} extends Controller
{
    /** 列表 GET /{BASE} */
    public function index(Request $request): Response
    {
        // 示例:分页查询
        // $page = (int)$request->input('page', 1);
        // return $this->ok(Model::query()->paginate(15, $page));
        return $this->ok(['items' => []]);
    }

    /** 创建 POST /{BASE} */
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            // 'title' => 'required|string|max:200',
        ]);

        // $model = Model::create($data);
        return $this->ok($data, '创建成功');
    }

    /** 详情 GET /{BASE}/{id} */
    public function show(Request $request): Response
    {
        $id = (int)$request->params['id'];
        return $this->ok(['id' => $id]);
    }

    /** 更新 PUT/PATCH /{BASE}/{id} */
    public function update(Request $request): Response
    {
        $id = (int)$request->params['id'];
        $data = $this->validate($request, [
            // 'title' => 'string|max:200',
        ]);

        return $this->ok(['id' => $id] + $data, '更新成功');
    }

    /** 删除 DELETE /{BASE}/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int)$request->params['id'];
        return $this->ok(['id' => $id], '删除成功');
    }
}

PHP;
        $base = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('Controller', '', $this->argument('name', 'Item'))) ?? 'item');
        return str_replace(
            ['{NAMESPACE}', '{CLASS}', '{BASE}'],
            [$namespace, $class, $base],
            $stub
        );
    }
}
