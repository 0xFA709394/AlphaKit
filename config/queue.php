<?php

declare(strict_types=1);

return [
    // database(默认,基于 jobs 表) / sync(同步执行,适合测试)
    'default' => env('QUEUE_DRIVER', 'database'),
];
