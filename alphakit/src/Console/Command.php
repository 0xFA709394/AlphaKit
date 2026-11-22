<?php

declare(strict_types=1);

namespace AlphaKit\Console;

/**
 * 命令基类
 *
 * class GreetCommand extends Command {
 *     protected string $signature = 'greet {name : 称呼} {--shout : 大写}';
 *     protected string $description = '打招呼';
 *     protected function handle(): int {
 *         $name = $this->argument('name');
 *         $this->info($this->option('shout') ? strtoupper($name) : $name);
 *         return self::SUCCESS;
 *     }
 * }
 *
 * 签名语法:
 *   {name}       必填参数    {name?} 可选参数
 *   {--force}    布尔选项    {--dir=} 带值选项
 */
abstract class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    protected string $signature = '';
    protected string $description = '';
    protected bool $hidden = false;

    /** @var array<string, mixed> 解析后的参数与选项(run 时注入) */
    protected array $arguments = [];
    protected array $options = [];

    /** 由 ConsoleApplication 调用 */
    public function runWith(array $arguments, array $options): int
    {
        $this->arguments = $arguments;
        $this->options = $options;
        $this->validate();
        return (int)($this->handle() ?? self::SUCCESS);
    }

    /** 校验必填参数 */
    protected function validate(): void
    {
        foreach ($this->definition() as $token => $spec) {
            if ($spec['required'] && $spec['type'] === 'argument' && !isset($this->arguments[$token])) {
                throw new \RuntimeException("命令 [{$this->signature()}] 缺少必填参数 {{$token}}");
            }
        }
    }

    /** @return mixed */
    protected function argument(string $name, $default = null)
    {
        return $this->arguments[$name] ?? $default;
    }

    /** @return mixed */
    protected function option(string $name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    protected function info(string $message): void
    {
        $this->line("\033[32m{$message}\033[0m");
    }

    protected function error(string $message): void
    {
        $this->line("\033[31m{$message}\033[0m");
    }

    protected function warn(string $message): void
    {
        $this->line("\033[33m{$message}\033[0m");
    }

    protected function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    /** 表格输出(CJK 双宽字符按 2 列对齐) */
    protected function table(array $headers, array $rows): void
    {
        $all = array_merge([$headers], $rows);
        $widths = [];
        foreach ($all as $row) {
            foreach (array_values($row) as $i => $cell) {
                $width = $this->displayWidth((string)$cell);
                $widths[$i] = max($widths[$i] ?? 0, $width);
            }
        }
        $line = fn (array $row) => ' ' . implode(' | ', array_map(
            fn ($i, $cell) => $this->padWidth((string)$cell, $widths[$i]),
            array_keys($widths),
            array_values(array_pad($row, count($widths), ''))
        ));
        $separator = '+' . implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)) . '+';

        $this->line($separator);
        $this->line($line($headers));
        $this->line($separator);
        foreach ($rows as $row) {
            $this->line($line($row));
        }
        $this->line($separator);
    }

    /** 终端显示宽度(CJK/全角算 2 列) */
    protected function displayWidth(string $string): int
    {
        $width = 0;
        foreach (mb_str_split($string) as $char) {
            $width += preg_match('/[\x{1100}-\x{115F}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}]/u', $char) ? 2 : 1;
        }
        return $width;
    }

    /** 按显示宽度右侧补空格 */
    protected function padWidth(string $string, int $length): string
    {
        $padding = $length - $this->displayWidth($string);
        return $padding > 0 ? $string . str_repeat(' ', $padding) : $string;
    }

    public function signature(): string
    {
        // 签名首段 = 命令名(不含参数定义)
        return trim(explode('{', $this->signature)[0]);
    }

    public function name(): string
    {
        return $this->signature();
    }

    public function description(): string
    {
        return $this->description;
    }

    public function hidden(): bool
    {
        return $this->hidden;
    }

    /** 完整签名(帮助用) */
    public function fullSignature(): string
    {
        return trim($this->signature);
    }

    /**
     * 解析签名中的 token 定义
     * @return array<string, array{type: string, required: bool, has_value: bool}>
     */
    public function definition(): array
    {
        $tokens = [];
        if (preg_match_all('/\{(.*?)\}/', $this->signature, $matches)) {
            foreach ($matches[1] as $token) {
                $token = trim($token);
                $hasValue = false;
                if (str_starts_with($token, '--')) {
                    $type = 'option';
                    $token = substr($token, 2);
                    // --dir= 表示带值
                    if (str_ends_with($token, '=')) {
                        $hasValue = true;
                        $token = rtrim($token, '=');
                    }
                    $required = false;
                } else {
                    $type = 'argument';
                    $required = !str_ends_with($token, '?');
                    $token = rtrim($token, '?');
                }
                // 去掉参数说明部分
                $token = trim(explode(' ', $token)[0]);
                $tokens[$token] = ['type' => $type, 'required' => $required, 'has_value' => $hasValue];
            }
        }
        return $tokens;
    }

    /** 执行命令 */
    abstract protected function handle(): ?int;
}
