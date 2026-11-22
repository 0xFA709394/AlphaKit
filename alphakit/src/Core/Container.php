<?php

declare(strict_types=1);

namespace AlphaKit\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * 轻量依赖注入容器
 *
 * 支持:
 * - bind()  绑定接口到实现 / 单例绑定
 * - make()  解析服务(构造函数自动装配,支持递归依赖)
 * - instance() 直接注入已实例化对象
 */
class Container
{
    /** @var array<string, Closure|string> 抽象到实现的绑定 */
    protected array $bindings = [];

    /** @var array<string, bool> 共享(单例)标记 */
    protected array $shared = [];

    /** @var array<string, mixed> 已实例化的单例 */
    protected array $instances = [];

    /** @var array<string, Closure> 扩展器 */
    protected array $extenders = [];

    /**
     * 绑定
     *
     * @param string          $abstract 抽象名(通常是接口或类名)
     * @param Closure|string  $concrete 闭包或类名,为 null 时使用 $abstract
     * @param bool            $shared   是否单例
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);
        $this->bindings[$abstract] = $concrete ?? $abstract;
        $this->shared[$abstract] = $shared;
    }

    /** 单例绑定 */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /** 直接注入实例 */
    public function instance(string $abstract, $object): void
    {
        $this->instances[$abstract] = $object;
    }

    /** 解析服务 */
    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->applyExtenders($this->instances[$abstract], $abstract);
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;

        $object = $concrete instanceof Closure
            ? $concrete($this)
            : $this->build($concrete);

        if (!empty($this->shared[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $this->applyExtenders($object, $abstract);
    }

    /** 通过反射构建类实例,自动解析构造函数依赖 */
    protected function build(string $concrete)
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new \RuntimeException("容器无法解析 [$concrete]: " . $e->getMessage(), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("[$concrete] 不可实例化(接口或抽象类),请先 bind() 绑定实现");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }

        $args = array_map(function (ReflectionParameter $param) use ($concrete) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->make($type->getName());
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new \RuntimeException("无法解析 [$concrete] 构造参数 \${$param->getName()},请提供默认值或类型提示");
        }, $constructor->getParameters());

        return $reflector->newInstanceArgs($args);
    }

    /** 注册解析后扩展器 */
    public function extend(string $abstract, Closure $extender): void
    {
        $this->extenders[$abstract][] = $extender;
    }

    protected function applyExtenders($object, string $abstract)
    {
        foreach ($this->extenders[$abstract] ?? [] as $extender) {
            $object = $extender($object, $this);
        }
        return $object;
    }

    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /** 当前是否已绑定 */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
}
