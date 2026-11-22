<?php

declare(strict_types=1);

/**
 * Web 路由(传统页面,含 Session)
 */

use App\Controllers\Web\PageController;
use AlphaKit\Core\Router;

return function (Router $router): void {
    $router->group(['middleware' => ['session']], function (Router $router): void {
        $router->get('/', [PageController::class, 'welcome'])->name('home');
        $router->get('/docs', [PageController::class, 'docs'])->name('docs');
        $router->get('/demo/session', [PageController::class, 'sessionDemo'])->name('session.demo');
    });
};
