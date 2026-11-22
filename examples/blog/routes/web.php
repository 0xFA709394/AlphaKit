<?php

declare(strict_types=1);

/**
 * Web 路由(视图/Session/命名路由/重定向/下载 演示)
 */

use AlphaKit\Core\Router;
use App\Controllers\Web\HomeController;

return function (Router $router): void {
    $router->group(['middleware' => ['session', 'timed:500']], function (Router $router): void {
        $router->get('/', [HomeController::class, 'home'])->name('home');
        $router->get('/posts/{id:\d+}', [HomeController::class, 'post'])->name('post.show');
        $router->get('/posts/{id:\d+}/export', [HomeController::class, 'exportPost'])->name('post.export');
        $router->get('/go/{id:\d+}', [HomeController::class, 'go'])->name('post.go');
        $router->get('/session-info', [HomeController::class, 'sessionInfo'])->name('session.info');
        $router->get('/archive/{page?}', [HomeController::class, 'archive'])->name('archive');
        $router->get('/legacy-home', [HomeController::class, 'legacyHome'])->name('legacy.home');
        $router->get('/login-form', [HomeController::class, 'loginForm'])->name('login.form');
        $router->post('/login', [HomeController::class, 'login'])->name('login.submit');
        $router->post('/logout', [HomeController::class, 'logout'])->name('logout');
    });
};
