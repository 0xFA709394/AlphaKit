<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * .env 文件加载器
 * 将项目根目录 .env 中的键值写入 $_ENV / getenv,支持引号与 # 注释
 */
class Dotenv
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过注释与非法行
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 去除引号包裹
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            // 行内注释
            if (str_contains($value, ' #')) {
                $value = trim(explode(' #', $value)[0]);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }
}
