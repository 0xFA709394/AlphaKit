<?php
/**
 * AlphaKit HTTP 入口(所有请求经由前端控制器分发)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/alphakit/bootstrap.php';

\AlphaKit\Core\Application::getInstance()->runHttp();
