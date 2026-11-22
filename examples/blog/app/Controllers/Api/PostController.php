<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AlphaKit\Core\Controller;
use AlphaKit\Core\Exception\HttpException;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Http\HttpClient;
use App\Contracts\SlugifierInterface;
use App\Events\PostPublished;
use App\Models\{Comment, Post};

/**
 * 文章 API(资源路由 + 缓存 + 事件 + 下载 + HttpClient 综合)
 */
final class PostController extends Controller
{
    public function __construct(protected SlugifierInterface $slugifier)
    {
        // 构造注入:ServiceProvider 绑定的接口实现
    }

    /** GET /api/posts?page=&per_page=&keyword= */
    public function index(Request $request): Response
    {
        $userId = (int)$request->user['id'];
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 10)));

        $query = Post::query()->where('user_id', '=', $userId);
        if (($kw = $request->input('keyword')) !== null && $kw !== '') {
            $query->whereLike('title', '%' . $kw . '%');
        }
        return $this->ok($query->orderBy('id', 'desc')->paginate($perPage, $page));
    }

    /** POST /api/posts —— 创建即发布,触发事件链路 */
    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:20000',
        ]);

        $post = Post::create([
            'user_id' => (int)$request->user['id'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug((string)$data['title']),
            'content' => $data['content'],
            'published' => 1,
            'views' => 0,
        ]);

        // 事件 → 监听器(审计日志) → 队列(sitemap);dispatch 返回各监听器回执
        $receipts = app(\AlphaKit\Core\Event\EventDispatcher::class)
            ->dispatch(new PostPublished((int)$post->id, (string)$post->title));

        cache_store()->delete('hot-posts'); // 缓存失效演示

        return $this->ok(['post' => $post, 'event_receipts' => $receipts], '发布成功')->withStatus(201);
    }

    /** GET /api/posts/{id:\d+} */
    public function show(Request $request): Response
    {
        return $this->ok($this->findOwned($request));
    }

    /** PUT /api/posts/{id:\d+} */
    public function update(Request $request): Response
    {
        $post = $this->findOwned($request);
        $data = $this->validate($request, [
            'title' => 'nullable|string|max:200',
            'content' => 'nullable|string|max:20000',
            'published' => 'nullable|boolean',
        ]);
        $updates = array_filter($data, fn ($v) => $v !== null);
        if (array_key_exists('published', $data)) {
            $updates['published'] = (int)(bool)$data['published'];
        }
        $post->update($updates);
        cache_store()->delete('hot-posts');
        return $this->ok($post, '更新成功');
    }

    /** DELETE /api/posts/{id:\d+} */
    public function destroy(Request $request): Response
    {
        $post = $this->findOwned($request);
        Comment::query()->where('post_id', '=', $post->id)->delete();
        $post->delete();
        return $this->ok(['id' => $post->id], '删除成功');
    }

    /** GET /api/posts/hot —— 缓存 getOrSet 模式 */
    public function hot(): Response
    {
        $hot = cache_store()->getOrSet('hot-posts', 300, function (): array {
            return Post::query()->where('published', '=', 1)->orderBy('views', 'desc')->limit(5)->get();
        });
        return $this->ok(['items' => $hot, 'cached_at' => now()]);
    }

    /** POST /api/posts/{id:\d+}/view —— 缓存原子计数 */
    public function view(Request $request): Response
    {
        $post = $this->findOwned($request);
        Post::query()->where('id', '=', $post->id)->increment('views');   // DB 原子自增
        $cached = cache_store()->increment('views:' . $post->id);          // 缓存计数器
        return $this->ok(['db_views' => (int)Post::find($post->id)->views, 'cached_views' => $cached]);
    }

    /** POST /api/posts/{id:\d+}/comments —— 关联 + 队列派发 */
    public function comment(Request $request): Response
    {
        $post = $this->findOwned($request);
        $data = $this->validate($request, [
            'author_name' => 'required|string|max:30',
            'body' => 'required|string|max:1000',
        ]);
        $comment = Comment::create([
            'post_id' => $post->id,
            'author_name' => $data['author_name'],
            'body' => $data['body'],
        ]);
        // 命名队列演示:通知走 notifications 队列(与 default 的 sitemap 队列分离)
        app(\AlphaKit\Queue\QueueManager::class)->push(new \App\Jobs\SendCommentNotification([
            'post_id' => (int)$post->id,
            'author_email' => (string)($request->user['email'] ?? ''),
        ]), 'notifications');
        return $this->ok($comment, '评论已提交,通知已入队')->withStatus(201);
    }

    /** GET /api/posts/{id:\d+}/export —— 文件下载演示 */
    public function export(Request $request): Response
    {
        $post = $this->findOwned($request);
        $dir = storage_path('app/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/post-' . $post->id . '.md';
        file_put_contents($file, "# {$post->title}\n\n{$post->content}\n");
        return Response::download($file, "post-{$post->slug}.md");
    }

    /** POST /api/relay/health —— HttpClient 调用本站 API(服务间调用演示) */
    public function relay(Request $request): Response
    {
        // 以当前请求的 scheme://host:port 为上游(任意端口均可用)
        $parts = parse_url($request->url());
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '127.0.0.1')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $resp = HttpClient::client()->timeout(5)->get($origin . '/api/health');

        if (!$resp->ok()) {
            throw new HttpException(502, '上游服务不可用');
        }
        return $this->ok([
            'via' => 'HttpClient',
            'upstream' => $resp->json(),
            'status' => $resp->status,
        ]);
    }

    /** 生成唯一 slug(重名追加短随机后缀;QueryBuilder::exists 演示) */
    private function uniqueSlug(string $title): string
    {
        $slug = $this->slugifier->make($title);
        if (Post::query()->where('slug', '=', $slug)->exists()) {
            $slug .= '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        }
        return $slug;
    }

    /** 越权防护:仅本人 */
    private function findOwned(Request $request): Post
    {
        $post = Post::findOrFail((int)$request->params['id']);
        if ((int)$post->user_id !== (int)$request->user['id']) {
            abort(403, '无权操作该文章');
        }
        return $post;
    }
}
