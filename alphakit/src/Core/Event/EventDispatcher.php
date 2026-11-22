<?php

declare(strict_types=1);

namespace AlphaKit\Core\Event;

/**
 * 事件调度器(观察者模式)
 *
 * 用法:
 *   $events->on(UserRegistered::class, function (UserRegistered $e) { ... });
 *   $events->dispatch(new UserRegistered($user));
 *
 * 监听器支持:闭包 / [类名, 方法] / 可调用对象类名(自动容器解析)
 */
class EventDispatcher
{
    /** @var array<class-string, array<int, mixed>> */
    protected array $listeners = [];

    /** 注册监听器:支持单个监听器或监听器列表(数组套数组自动展开) */
    public function on(string $eventName, $listener): void
    {
        // 监听器列表形态: [[Class,'m'],[Class,'m']] —— 逐个展开注册
        if (is_array($listener) && isset($listener[0]) && is_array($listener[0])) {
            foreach ($listener as $single) {
                $this->on($eventName, $single);
            }
            return;
        }
        $this->listeners[$eventName][] = $listener;
    }

    /** 注册一组监听器(类名 => 监听器数组) */
    public function subscribe(array $map): void
    {
        foreach ($map as $event => $listeners) {
            foreach ((array)$listeners as $listener) {
                $this->on($event, $listener);
            }
        }
    }

    /** 派发事件,返回监听器返回值数组 */
    public function dispatch(object $event): array
    {
        $results = [];
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $results[] = $this->invoke($listener, $event);
        }
        return $results;
    }

    protected function invoke($listener, object $event)
    {
        if ($listener instanceof \Closure) {
            return $listener($event);
        }
        if (is_array($listener)) {
            [$class, $method] = $listener;
            $instance = is_object($class) ? $class : app($class);
            return $instance->{$method}($event);
        }
        if (is_string($listener) && class_exists($listener)) {
            $instance = app($listener);
            return $instance($event);
        }
        throw new \RuntimeException('不支持的监听器类型: ' . var_export($listener, true));
    }

    /** 某事件是否有监听器 */
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }
}
