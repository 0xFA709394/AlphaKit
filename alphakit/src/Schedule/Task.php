<?php

declare(strict_types=1);

namespace AlphaKit\Schedule;

/**
 * 调度任务:闭包或 CLI 命令 + 时间表达式
 */
class Task
{
    protected CronExpression $cron;

    protected string $taskName = '';

    public function __construct(protected string $type, protected mixed $payload)
    {
        $this->cron = new CronExpression('* * * * *');
    }

    public function cron(string $expression): self
    {
        $this->cron = new CronExpression($expression);
        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        return $this->cron($minute . ' * * * *');
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /** 每天 hh:mm */
    public function dailyAt(string $time): self
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return $this->cron(trim($m) . ' ' . trim($h) . ' * * *');
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /** 每周几 hh:mm(0=周日) */
    public function weeklyOn(int $weekday, string $time = '00:00'): self
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return $this->cron(trim($m) . ' ' . trim($h) . ' * * ' . $weekday);
    }

    public function name(string $name): self
    {
        $this->taskName = $name;
        return $this;
    }

    public function isDue(int $timestamp): bool
    {
        return $this->cron->isDue($timestamp);
    }

    /** 执行任务 */
    public function execute(): bool
    {
        try {
            if ($this->type === 'closure') {
                ($this->payload)();
            } else {
                $php = PHP_BINARY;
                $exit = null;
                passthru(
                    sprintf('%s %s %s 2>&1', escapeshellarg($php), escapeshellarg(base_path('bin/alphakit')), escapeshellcmd($this->payload)),
                    $exit
                );
                return $exit === 0;
            }
            return true;
        } catch (\Throwable $e) {
            logger('schedule')->error('调度任务失败: ' . $this->label(), ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function label(): string
    {
        return $this->taskName !== ''
            ? $this->taskName
            : ($this->type === 'command' ? 'command: ' . $this->payload : 'closure #' . spl_object_id($this));
    }

    public function expression(): string
    {
        return $this->cron->expression();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }
}
