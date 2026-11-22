<?php

declare(strict_types=1);

namespace AlphaKit\Console;

/**
 * 控制台应用
 *
 * php bin/alphakit <command> [args] [--options]
 *
 * 参数解析:
 *   positional        -> 位置参数(按命令签名顺序命名)
 *   --key=value       -> 选项
 *   --flag            -> 布尔选项
 *
 * 命令注册来源:
 *   1. 框架内置命令(Commands/)
 *   2. config/app.php 的 commands 数组(应用自定义命令)
 */
class ConsoleApplication
{
    /** @var array<string, Command> */
    protected array $commands = [];

    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    protected function registerDefaultCommands(): void
    {
        $builtins = [
            Commands\ServeCommand::class,
            Commands\RoutesCommand::class,
            Commands\MigrateCommand::class,
            Commands\MigrateRollbackCommand::class,
            Commands\MigrateResetCommand::class,
            Commands\MigrateStatusCommand::class,
            Commands\DbSeedCommand::class,
            Commands\QueueWorkCommand::class,
            Commands\QueueFailedCommand::class,
            Commands\ScheduleRunCommand::class,
            Commands\ScheduleListCommand::class,
            Commands\MakeCommand::class,
            Commands\MakeController::class,
            Commands\MakeModel::class,
            Commands\MakeMigration::class,
            Commands\MakeJob::class,
            Commands\MakeMiddleware::class,
            Commands\KeyGenerateCommand::class,
            Commands\EnvCommand::class,
        ];

        foreach ($builtins as $class) {
            $this->add(new $class());
        }

        // 应用自定义命令
        foreach ((array)config('app.commands', []) as $class) {
            if (class_exists($class)) {
                $this->add(new $class());
            }
        }
    }

    public function add(Command $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * 执行入口
     *
     * @param array $argv CLI 参数(含脚本名)
     */
    public function run(array $argv): int
    {
        array_shift($argv); // 去掉脚本名

        $name = $argv[0] ?? null;

        if ($name === null || $name === 'help' || $name === '--help' || $name === '-h' || $name === 'list') {
            return $this->showHelp();
        }

        if (!isset($this->commands[$name])) {
            $suggestion = $this->suggest($name);
            fwrite(STDERR, "\033[31m未知命令: {$name}\033[0m" . PHP_EOL);
            if ($suggestion !== null) {
                fwrite(STDERR, "是否想用: \033[32m{$suggestion}\033[0m" . PHP_EOL);
            }
            return Command::FAILURE;
        }

        [$arguments, $options] = $this->parseArguments(array_slice($argv, 1), $this->commands[$name]);

        try {
            return $this->commands[$name]->runWith($arguments, $options);
        } catch (\Throwable $e) {
            fwrite(STDERR, "\033[31m[" . $this->commands[$name]->name() . "] " . $e->getMessage() . "\033[0m" . PHP_EOL);
            if (config('app.debug')) {
                fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
            }
            return Command::FAILURE;
        }
    }

    /**
     * 解析参数:位置参数按签名顺序赋名
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function parseArguments(array $raw, Command $command): array
    {
        $options = [];
        $positional = [];

        foreach ($raw as $item) {
            if (str_starts_with($item, '--')) {
                $body = substr($item, 2);
                if (str_contains($body, '=')) {
                    [$key, $value] = explode('=', $body, 2);
                    $options[$key] = $value;
                } else {
                    $options[$body] = true;
                }
            } else {
                $positional[] = $item;
            }
        }

        // 按签名定义为位置参数命名
        $arguments = [];
        $argNames = [];
        foreach ($command->definition() as $token => $spec) {
            if ($spec['type'] === 'argument') {
                $argNames[] = $token;
            }
        }
        foreach ($positional as $i => $value) {
            if (isset($argNames[$i])) {
                $arguments[$argNames[$i]] = $value;
            }
        }

        return [$arguments, $options];
    }

    /** 命令名相近建议 */
    protected function suggest(string $input): ?string
    {
        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach (array_keys($this->commands) as $name) {
            $distance = levenshtein($input, $name);
            if ($distance < $bestScore && $distance <= max(3, (int)(strlen($input) / 3))) {
                $best = $name;
                $bestScore = $distance;
            }
        }
        return $best;
    }

    protected function showHelp(): int
    {
        echo "\033[1;36mAlphaKit\033[0m \033[90m" . (config('app.version', '1.0.0')) . "\033[0m  快速开发框架" . PHP_EOL . PHP_EOL;
        echo "\033[1m用法:\033[0m" . PHP_EOL;
        echo "  php bin/alphakit <command> [arguments] [--options]" . PHP_EOL . PHP_EOL;
        echo "\033[1m可用命令:\033[0m" . PHP_EOL;

        $rows = [];
        foreach ($this->commands as $command) {
            if ($command->hidden()) {
                continue;
            }
            $rows[] = [$command->fullSignature(), $command->description()];
        }
        $width = max(array_map('strlen', array_column($rows, 0)));
        foreach ($rows as [$sig, $desc]) {
            echo "  \033[32m" . str_pad($sig, $width + 2) . "\033[0m{$desc}" . PHP_EOL;
        }
        echo PHP_EOL;
        return Command::SUCCESS;
    }

    /** @return Command[] */
    public function commands(): array
    {
        return $this->commands;
    }
}
