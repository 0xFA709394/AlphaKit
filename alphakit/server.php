<?php
/**
 * php -S 路由脚本:静态资源直出,其余进入框架
 */

declare(strict_types=1);

// 静态资源(public/ 下存在则直接返回)
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . '/../public' . $uri;
if ($uri !== '/' && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false; // 交由内置服务器输出静态文件
}

require __DIR__ . '/../public/index.php';
