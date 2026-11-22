<?php

declare(strict_types=1);

return [
    'path' => storage_path('logs'),
    // debug/info/notice/warning/error/critical/alert/emergency
    'level' => env('LOG_LEVEL', 'debug'),
    // 按天分文件
    'daily' => true,
];
