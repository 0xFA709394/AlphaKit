<?php

declare(strict_types=1);

namespace AlphaKit\Console\Commands;

use AlphaKit\Console\Command;
use AlphaKit\Core\Router;

/**
 * 路由列表: php bin/alphakit routes
 */
class RoutesCommand extends Command
{
    protected string $signature = 'routes';
    protected string $description = '列出全部已注册路由';

    protected function handle(): ?int
    {
        /** @var Router $router */
        $router = app(Router::class);

        $rows = [];
        foreach ($router->all() as $route) {
            $handler = $route->handler;
            if (is_array($handler)) {
                $target = $handler[0] . '@' . $handler[1];
            } elseif ($handler instanceof \Closure) {
                $target = 'Closure';
            } else {
                $target = get_debug_type($handler);
            }
            $rows[] = [$route->method, $route->path, $target, implode(',', $route->middleware) ?: '-'];
        }

        $this->table(['METHOD', 'URI', 'HANDLER', 'MIDDLEWARE'], $rows);
        return self::SUCCESS;
    }
}
