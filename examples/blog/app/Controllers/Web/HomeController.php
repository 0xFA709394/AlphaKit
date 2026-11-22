<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Core\Session;
use App\Models\Post;

/**
 * Web 页面(视图 + Session + 缓存 演示)
 */
final class HomeController extends Controller
{
    /** GET / —— 缓存预热 + 视图渲染 */
    public function home(Request $request): Response
    {
        $posts = cache_store()->getOrSet('web-home-posts', 120, function (): array {
            return Post::query()->where('published', '=', 1)->orderBy('views', 'desc')->limit(10)->get();
        });

        /** @var Session $session */
        $session = app(Session::class);
        return $this->render('pages/home', [
            'posts' => $posts,
            'sessionUser' => $session->get('user_name'),
        ]);
    }

    /** GET /posts/{id:\d+} —— 详情 + 评论(关联) */
    public function post(Request $request): Response
    {
        $id = (int)$request->params['id'];
        $post = Post::findOrFail($id);
        if (!$post->published) {
            abort(404, '文章未发布');
        }
        return $this->render('pages/post', [
            'post' => $post,
            'comments' => $post->comments(),
            'author' => $post->author(),
        ]);
    }

    /** GET /go/{id:\d+} —— 命名路由 + 重定向 */
    public function go(Request $request): Response
    {
        $id = (int)$request->params['id'];
        Post::findOrFail($id); // 确认存在
        return Response::redirect(\url('post.show', ['id' => $id]));
    }

    /** GET /posts/{id:\d+}/export —— Markdown 导出下载(download 演示) */
    public function exportPost(Request $request): Response
    {
        $post = Post::findOrFail((int)$request->params['id']);
        if (!$post->published) {
            abort(404, '文章未发布');
        }
        $file = storage_path('app/exports/web-post-' . $post->id . '.md');
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, "# {$post->title}\n\n{$post->content}\n\n---\nvia AlphaBlog\n");
        return Response::download($file, "{$post->slug}.md");
    }

    /** GET /login-form */
    public function loginForm(Request $request): Response
    {
        return $this->render('pages/login');
    }

    /** POST /login —— 校验 + regenerate + flash + redirect 全套 */
    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:30',
        ]);

        /** @var Session $session */
        $session = app(Session::class);
        $session->regenerate();                       // 防会话固定
        $session->put('user_name', $data['name']);
        $session->flash('message', '欢迎回来,' . $data['name'] . '!');  // 一次性提示

        return Response::redirect(\url('home'));
    }

    /** POST /logout */
    public function logout(Request $request): Response
    {
        app(Session::class)->flush();
        return Response::redirect(\url('home'));
    }

    /** GET /session-info —— Session 计数演示 */
    public function sessionInfo(Request $request): Response
    {
        /** @var Session $session */
        $session = app(Session::class);
        $visits = (int)$session->get('visits', 0) + 1;
        $session->put('visits', $visits);
        return $this->render('pages/session', [
            'visits' => $visits,
            'sessionId' => substr($session->id(), 0, 8),
        ]);
    }
}
