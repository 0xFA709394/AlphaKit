<?php
/**
 * Blog 示例项目 —— php -S 路由脚本
 * 静态资源(realpath 限定 public 内)直出,其余进入框架
 */

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicRoot = realpath(__DIR__ . '/public');

if ($publicRoot !== false && $uri !== '/') {
    $file = realpath($publicRoot . $uri);
    if ($file !== false
        && str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($file)
        && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
    ) {
        return false;
    }
}

require __DIR__ . '/public/index.php';
