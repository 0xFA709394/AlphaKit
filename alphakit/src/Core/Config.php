<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 配置仓库:点号语法读写,懒加载 config/ 目录下的 php 文件
 */
class Config
{
    /** @var array<string, array> 已加载配置 */
    protected array $items = [];

    protected string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? config_path();
    }

    /**
     * 读取配置,支持 "file.key.subkey"
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        $this->load($file);

        $value = $this->items[$file] ?? null;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** 写入配置(运行时) */
    public function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        $this->load($file);

        $target = &$this->items[$file];
        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }
        $target = $value;
    }

    /** 加载配置文件(幂等) */
    protected function load(string $file): void
    {
        if (isset($this->items[$file])) {
            return;
        }
        $path = $this->configPath . '/' . $file . '.php';
        $this->items[$file] = is_file($path) ? require $path : [];
    }
}
