<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 服务提供者基类
 * 在 Application 启动时先执行全部 register()(仅绑定),
 * 再执行全部 boot()(可安全使用其他服务)
 */
abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    /** 注册绑定(不要在此使用其他服务) */
    public function register(): void
    {
    }

    /** 启动逻辑(可使用所有已注册服务) */
    public function boot(): void
    {
    }
}
