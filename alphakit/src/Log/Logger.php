<?php

declare(strict_types=1);

namespace AlphaKit\Log;

/**
 * 文件日志器(PSR-3 风格级别)
 *
 * logger()->info('message', ['context' => '...']);
 * logger('payment')->warning('金额异常', ['amount' => -1]);
 *
 * 按 "日志名-日期.log" 分文件存储于 storage/logs
 */
class Logger
{
    public const LEVELS = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4, 'critical' => 5, 'alert' => 6, 'emergency' => 7];

    /** 最低记录级别 */
    protected string $minimumLevel = 'debug';

    public function __construct(
        protected string $channel = 'app',
        protected ?string $directory = null,
        protected ?string $timezone = null
    ) {
        $this->directory ??= config('log.path', storage_path('logs'));
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
        $configured = config('log.level', 'debug');
        if (is_string($configured) && isset(self::LEVELS[$configured])) {
            $this->minimumLevel = $configured;
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** 写入一条日志 */
    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 99) < (self::LEVELS[$this->minimumLevel] ?? 0)) {
            return;
        }

        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $message,
            $context !== [] ? (string)(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]') : ''
        );

        @file_put_contents($this->file(), $line, FILE_APPEND | LOCK_EX);
    }

    protected function file(): string
    {
        $daily = config('log.daily', true);
        $suffix = $daily ? '-' . date('Y-m-d') : '';
        return rtrim($this->directory, '/') . '/' . $this->channel . $suffix . '.log';
    }
}
