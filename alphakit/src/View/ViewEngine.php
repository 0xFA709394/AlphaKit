<?php

declare(strict_types=1);

namespace AlphaKit\View;

use AlphaKit\Core\Response;

/**
 * 视图引擎:原生 PHP 模板 + 布局继承 + 片段(section)
 *
 * 视图中:
 *   $this->extend('layouts/app');          // 继承布局
 *   $this->start('content'); ... $this->end();  // 定义片段
 *   $this->insert('partials/header', $data);     // 引入子视图
 *
 * 布局/普通视图中直接使用数据变量与 e() 转义函数
 */
class ViewEngine
{
    /** @var string|null 当前子视图要继承的布局 */
    protected ?string $layout = null;

    /** @var array<string, string> 片段内容 */
    protected array $sections = [];

    /** @var array<int, string> section 栈 */
    protected array $sectionStack = [];

    protected string $viewPath;

    public function __construct(?string $viewPath = null)
    {
        $this->viewPath = rtrim((string)($viewPath ?? config('view.path', base_path('app/views'))), '/');
    }

    /**
     * 渲染视图为 Response
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): Response
    {
        return Response::html($this->renderString($template, $data));
    }

    /**
     * 渲染为字符串
     *
     * @param array<string, mixed> $data
     */
    public function renderString(string $template, array $data = []): string
    {
        $this->layout = null;
        $this->sections = [];

        $content = $this->renderFile($template, $data);

        // 有布局则继续渲染布局(子内容保存在 content section)
        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->sections['content'] ??= $content;
            $content = $this->renderFile($layout, $data);
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
    protected function renderFile(string $template, array $data): string
    {
        $file = $this->resolve($template);
        if (!is_file($file)) {
            throw new \RuntimeException("视图 [$template] 不存在 ($file)");
        }

        $renderer = $this;
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    protected function resolve(string $template): string
    {
        return $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';
    }

    // ------------------------------------------------- 模板内 API

    /** 声明继承布局 */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    /** 开始片段 */
    public function start(string $section): void
    {
        $this->sectionStack[] = $section;
        ob_start();
    }

    /** 结束片段并保存 */
    public function end(): void
    {
        $section = array_pop($this->sectionStack);
        if ($section === null) {
            throw new \RuntimeException('end() 没有 start() 与之匹配');
        }
        $this->sections[$section] = (string)ob_get_clean();
    }

    /** 输出片段(带默认值) */
    public function section(string $section, string $default = ''): string
    {
        return $this->sections[$section] ?? $default;
    }

    /** 引入子视图 */
    public function insert(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    /** 转义输出 */
    public function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
