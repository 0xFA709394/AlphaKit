<?php

declare(strict_types=1);

namespace AlphaKit\Schedule;

/**
 * 任务调度器
 *
 * 在 app/schedule.php 中注册:
 *   $schedule->call(fn() => User::where('expired', 1)->delete())->dailyAt('03:00')->name('清理过期用户');
 *   $schedule->command('emails:send')->everyFiveMinutes();
 *   $schedule->command('backup:run')->cron('0 2 * * *');
 *
 * 系统cron每分钟执行: * * * * * php /path/bin/alphakit schedule:run
 */
class Scheduler
{
    /** @var Task[] */
    protected array $tasks = [];

    /** 注册闭包任务 */
    public function call(callable $callback): Task
    {
        return $this->add(new Task('closure', $callback));
    }

    /** 注册命令任务(php bin/alphakit <command>) */
    public function command(string $command): Task
    {
        return $this->add(new Task('command', $command));
    }

    protected function add(Task $task): Task
    {
        $this->tasks[] = $task;
        return $task;
    }

    /** 到期任务列表 */
    public function dueTasks(int $timestamp): array
    {
        return array_values(array_filter($this->tasks, fn (Task $t) => $t->isDue($timestamp)));
    }

    public function all(): array
    {
        return $this->tasks;
    }

    /** 执行到期任务,返回 [name => 成功?] */
    public function runDue(int $timestamp): array
    {
        $results = [];
        foreach ($this->dueTasks($timestamp) as $task) {
            $results[$task->label()] = $task->execute();
        }
        return $results;
    }

    /** 加载应用注册文件(默认 app/schedule.php,文件内使用 $schedule 变量) */
    public function loadApplicationSchedule(): void
    {
        $file = base_path('app/schedule.php');
        if (is_file($file)) {
            $schedule = $this;
            require $file;
        }
    }
}
