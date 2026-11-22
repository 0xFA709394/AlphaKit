<?php

declare(strict_types=1);

namespace AlphaKit\Schedule;

/**
 * 标准 5 段 cron 表达式解析与匹配
 * 分 时 日 月 周  (支持 * , - / 与英文别名 JAY MON...)
 */
class CronExpression
{
    public function __construct(protected string $expression)
    {
    }

    /** 是否在给定时刻应触发 */
    public function isDue(int $timestamp): bool
    {
        $parts = preg_split('/\s+/', trim($this->expression));
        if ($parts === false || count($parts) !== 5) {
            throw new \InvalidArgumentException("非法 cron 表达式: {$this->expression}");
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return $this->fieldMatches($minute, (int)date('i', $timestamp), 0, 59)
            && $this->fieldMatches($hour, (int)date('G', $timestamp), 0, 23)
            && $this->fieldMatches($day, (int)date('j', $timestamp), 1, 31)
            && $this->fieldMatches($month, (int)date('n', $timestamp), 1, 12)
            && $this->weekdayMatches($weekday, (int)date('w', $timestamp));
    }

    /** 单字段匹配:支持 * , - / */
    protected function fieldMatches(string $field, int $value, int $min, int $max): bool
    {
        if (str_contains($field, ',')) {
            foreach (explode(',', $field) as $sub) {
                if ($this->fieldMatches($sub, $value, $min, $max)) {
                    return true;
                }
            }
            return false;
        }

        // 步长 a-b/n 或 */n
        $step = 1;
        if (str_contains($field, '/')) {
            [$field, $stepPart] = explode('/', $field, 2);
            $step = max(1, (int)$stepPart);
        }

        if ($field === '*') {
            return ($value - $min) % $step === 0;
        }

        if (str_contains($field, '-')) {
            [$from, $to] = explode('-', $field, 2);
            return $value >= (int)$from && $value <= (int)$to && ($value - (int)$from) % $step === 0;
        }

        $single = (int)$field;
        return $step > 1
            ? ($value >= $single && $value <= $max && ($value - $single) % $step === 0)
            : $value === $single;
    }

    /** 周字段:0=周日..6=周六 */
    protected function weekdayMatches(string $field, int $value): bool
    {
        return $this->fieldMatches($field === '7' ? '0' : $field, $value, 0, 6);
    }

    public function expression(): string
    {
        return $this->expression;
    }
}
