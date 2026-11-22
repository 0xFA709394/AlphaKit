<?php
/**
 * Blog 示例项目 —— HTTP 入口
 * 演示:基于 AlphaKit 的独立项目如何指定自身 BASE_PATH
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require dirname(__DIR__, 3) . '/alphakit/bootstrap.php';

\AlphaKit\Core\Application::getInstance()->runHttp();
