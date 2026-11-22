<?php
/**
 * AlphaKit 引导文件
 * 零依赖启动:不依赖 composer,内置 PSR-4 自动加载器。
 * 若项目已通过 composer 安装,则优先使用 composer 的 autoloader。
 */

declare(strict_types=1);

define('ALPHAKIT_START', microtime(true));
// 允许外部项目(如 examples/)预先定义 BASE_PATH 指向自身根目录,
// 使 App\ 自动加载 / config / storage / routes 全部路由到该项目
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// 优先使用 composer autoloader(存在 vendor 时)
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    // 内置 PSR-4 自动加载器:
    //   AlphaKit\ -> 框架自身 src/(相对本文件,与 BASE_PATH 无关)
    //   App\      -> BASE_PATH/app/(外部项目可重定向)
    spl_autoload_register(function (string $class): void {
        $prefixes = [
            'AlphaKit\\' => __DIR__ . '/src/',
            'App\\'      => BASE_PATH . '/app/',
        ];
        foreach ($prefixes as $prefix => $dir) {
            if (strncmp($class, $prefix, strlen($prefix)) === 0) {
                $relative = substr($class, strlen($prefix));
                $file = $dir . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        }
    });
}

require __DIR__ . '/src/Support/helpers.php';
