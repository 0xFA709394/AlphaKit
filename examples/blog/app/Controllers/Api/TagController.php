<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Database\QueryBuilder;
use App\Models\Tag;

/**
 * Tag 资源控制器(make:controller --api --resource 生成后填充)
 *
 * 演示:whereIn / whereBetween / orWhere 条件组 / upsert / insertMany / sum / max
 * 路由: resource('/tags', TagController::class) (auth)
 */
final class TagController extends Controller
{
    /** GET /api/tags —— whereBetween + 聚合(sum/max) */
    public function index(Request $request): Response
    {
        $min = (int)$request->input('min_weight', 0);
        $max = (int)$request->input('max_weight', 1000);

        $tags = Tag::query()
            ->whereBetween('weight', $min, $max)
            ->orderBy('weight', 'desc')
            ->get();

        return $this->ok([
            'items' => $tags,
            'total_weight' => Tag::totalWeight(),
            'max_weight' => (int)(Tag::query()->max('weight') ?? 0),
        ]);
    }

    /** POST /api/tags */
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:50|unique:tags,name',
            'weight' => 'nullable|integer|min:0|max:1000',
        ]);
        $tag = Tag::create(['name' => $data['name'], 'weight' => (int)($data['weight'] ?? 1)]);
        return $this->ok($tag, '标签已创建')->withStatus(201);
    }

    /** GET /api/tags/{id} */
    public function show(Request $request): Response
    {
        return $this->ok(Tag::findOrFail((int)$request->params['id']));
    }

    /** PUT /api/tags/{id} */
    public function update(Request $request): Response
    {
        $tag = Tag::findOrFail((int)$request->params['id']);
        $data = $this->validate($request, [
            'name' => 'nullable|string|max:50',
            'weight' => 'nullable|integer|min:0|max:1000',
        ]);
        $tag->update(array_filter($data, fn ($v) => $v !== null));
        return $this->ok($tag, '标签已更新');
    }

    /** DELETE /api/tags/{id} */
    public function destroy(Request $request): Response
    {
        $tag = Tag::findOrFail((int)$request->params['id']);
        $tag->delete();
        return $this->ok(['id' => $tag->id], '标签已删除');
    }

    /** POST /api/tags/bulk —— insertMany 批量插入演示 */
    public function bulk(Request $request): Response
    {
        $data = $this->validate($request, [
            'names' => 'required|array|min:1|max:20',
            'names.*' => 'string|max:50',
        ]);
        $rows = array_map(fn ($i, $name) => [
            'name' => $name . '-' . substr(bin2hex(random_bytes(3)), 0, 4),
            'weight' => ($i + 1) * 10,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_keys($data['names']), array_values($data['names']));

        QueryBuilder::table('tags')->insertMany($rows);
        return $this->ok(['inserted' => count($rows)], '批量插入成功')->withStatus(201);
    }

    /** POST /api/tags/sync —— upsert(存在即更新)演示 */
    public function sync(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:50',
            'weight' => 'required|integer|min:0|max:1000',
        ]);
        QueryBuilder::table('tags')->upsert(
            ['name' => $data['name'], 'weight' => $data['weight'], 'created_at' => now(), 'updated_at' => now()],
            ['name']
        );
        return $this->ok(Tag::query()->where('name', '=', $data['name'])->first(), '同步完成');
    }

    /** GET /api/tags/filter —— orWhere 条件组 + whereIn 演示 */
    public function filter(Request $request): Response
    {
        $data = $this->validate($request, [
            'names' => 'nullable|string',
            'min' => 'nullable|integer',
        ]);

        $query = Tag::query();
        if ($names = $request->input('names')) {
            // whereIn:名称集合
            $query->whereIn('name', array_filter(array_map('trim', explode(',', (string)$names))));
        }
        $min = $data['min'] ?? null;
        if ($min !== null && $min !== '') {
            // 条件组:(weight >= min OR weight = 0)
            $query->whereGroup(function ($q) use ($min) {
                $q->where('weight', '>=', (int)$min)->orWhere('weight', '=', 0);
            });
        }
        return $this->ok(['items' => $query->orderBy('id')->limit(20)->get()]);
    }

    /** POST /api/tags/warm —— 派发命名队列任务(队列名演示) */
    public function warm(Request $request): Response
    {
        // 命名队列:default 之外的第二个队列
        app(\AlphaKit\Queue\QueueManager::class)->push(new \App\Jobs\WarmTagCache(), 'tags');
        return $this->ok(['queued' => 'tags 队列已接收预热任务']);
    }
}
