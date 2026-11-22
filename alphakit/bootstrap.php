<?php
/**
 * AlphaKit 引导文件
 * 零依赖启动:不依赖 composer,内置 PSR-4 自动加载器。
 * 若项目已通过 composer 安装,则优先使用 composer 的 autoloader。
 */

declare(strict_types=1);

define('ALPHAKIT_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));

// 优先使用 composer autoloader(存在 vendor 时)
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // 内置 PSR-4 自动加载器:AlphaKit\ -> alphakit/src/, App\ -> app/
    spl_autoload_register(function (string $class): void {
        $prefixes = [
            'AlphaKit\\' => BASE_PATH . '/alphakit/src/',
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

require BASE_PATH . '/alphakit/src/Support/helpers.php';
