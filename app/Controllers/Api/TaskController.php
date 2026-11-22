<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use App\Models\Task;

/**
 * 任务资源控制器(标准 RESTful CRUD)
 * 路由: $router->resource('/tasks', TaskController::class) + auth 中间件
 */
final class TaskController extends Controller
{
    /** GET /api/tasks?page=1&per_page=15&done=0&keyword=xx */
    public function index(Request $request): Response
    {
        $userId = (int)$request->user['id'];
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(100, max(1, (int)$request->input('per_page', 15)));

        $query = Task::query()->where('user_id', '=', $userId);

        // 可选过滤
        if ($request->input('done') !== null) {
            $query->where('done', '=', (int)(bool)$request->input('done'));
        }
        if (($keyword = $request->input('keyword')) !== null && $keyword !== '') {
            $query->whereLike('title', '%' . $keyword . '%');
        }

        return $this->ok($query->orderBy('id', 'desc')->paginate($perPage, $page));
    }

    /** POST /api/tasks */
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
        ]);

        $task = Task::create([
            'user_id' => (int)$request->user['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'done' => 0,
            'tags' => isset($data['tags']) ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return $this->ok($task, '创建成功')->withStatus(201);
    }

    /** GET /api/tasks/{id} */
    public function show(Request $request): Response
    {
        return $this->ok($this->findOwnedTask($request));
    }

    /** PUT/PATCH /api/tasks/{id} */
    public function update(Request $request): Response
    {
        $task = $this->findOwnedTask($request);

        $data = $this->validate($request, [
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'done' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ]);

        $updates = array_filter($data, fn ($v) => $v !== null);
        if (isset($data['tags'])) {
            $updates['tags'] = json_encode($data['tags'], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('done', $data)) {
            $updates['done'] = (int)(bool)$data['done'];
        }

        $task->update($updates);
        return $this->ok($task, '更新成功');
    }

    /** DELETE /api/tasks/{id} */
    public function destroy(Request $request): Response
    {
        $task = $this->findOwnedTask($request);
        $task->delete();
        return $this->ok(['id' => $task->id], '删除成功');
    }

    /** GET /api/users/{id}/tasks (关联演示;仅可查看自己的任务) */
    public function forUser(Request $request): Response
    {
        $userId = (int)$request->params['id'];
        if ($userId !== (int)$request->user['id']) {
            \abort(403, '仅可查看自己的任务');
        }
        $user = \App\Models\User::findOrFail($userId);
        return $this->ok($user->tasks());
    }

    /** 只能操作自己的任务 */
    private function findOwnedTask(Request $request): Task
    {
        $task = Task::findOrFail((int)$request->params['id']);
        if ((int)$task->user_id !== (int)$request->user['id']) {
            \abort(403, '无权操作该任务');
        }
        return $task;
    }
}
