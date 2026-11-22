<?php
/**
 * php -S 路由脚本:静态资源直出(严格限定在 public/ 内),其余进入框架
 *
 * 安全:realpath 规范化后必须仍位于 public 目录内,
 * 否则 /../.env 这类穿越请求会绕过路由脚本泄露项目文件
 */

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicRoot = realpath(__DIR__ . '/../public');

if ($publicRoot !== false && $uri !== '/') {
    $file = realpath($publicRoot . $uri);
    if ($file !== false
        && str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($file)
        && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
    ) {
        return false; // public 内的静态文件,交由内置服务器输出
    }
}

require __DIR__ . '/../public/index.php';
