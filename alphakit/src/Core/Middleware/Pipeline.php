<?php

declare(strict_types=1);

namespace AlphaKit\Core\Middleware;

use AlphaKit\Core\Container;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;

/**
 * 中间件管道(洋葱模型)
 * 全局中间件 + 路由中间件依次包裹控制器执行
 */
class Pipeline
{
    /** @var array<string> 中间件类名列表(或 "别名:参数") */
    protected array $middleware = [];

    public function __construct(protected Container $container)
    {
    }

    /**
     * @param array<string> $middleware
     */
    public function through(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * 执行管道,最终执行 $destination
     *
     * @param callable(Request): Response $destination
     */
    public function then(Request $request, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (callable $next, string $middleware) {
                return function (Request $request) use ($next, $middleware): Response {
                    [$class, $parameters] = $this->parse($middleware);
                    $instance = $this->container->make($class);
                    if (!$instance instanceof MiddlewareInterface) {
                        throw new \RuntimeException("[$class] 未实现 MiddlewareInterface");
                    }
                    $handler = method_exists($instance, 'handle')
                        ? [$instance, 'handle']
                        : [$instance, 'process']; // 兼容 PSR-15 风格命名
                    return $handler($request, $next, ...$this->castParameters($handler, $parameters));
                };
            },
            function (Request $request) use ($destination): Response {
                return $destination($request);
            }
        );

        return $pipeline($request);
    }

    /** 解析 "Class:param1,param2" 形式 */
    protected function parse(string $middleware): array
    {
        if (str_contains($middleware, ':')) {
            [$class, $params] = explode(':', $middleware, 2);
            return [$class, explode(',', $params)];
        }
        return [$middleware, []];
    }

    /**
     * 依据 handle() 的类型提示转换字符串参数(如 "60" -> 60)
     * handle 签名约定:handle(Request $request, callable $next, ...$middlewareParams)
     * 因此中间件参数与 handle 的第 3 个参数(offset 2)起一一对应
     */
    protected function castParameters(array $handler, array $parameters): array
    {
        try {
            $ref = new \ReflectionMethod($handler[0], $handler[1]);
            $handleParams = $ref->getParameters();

            foreach ($parameters as $index => $value) {
                $param = $handleParams[$index + 2] ?? null;
                if ($param === null || !is_string($value)) {
                    continue;
                }
                $type = $param->getType();
                if (!$type instanceof \ReflectionNamedType) {
                    continue;
                }
                if ($type->getName() === 'int' && is_numeric($value) && (string)(int)$value === $value) {
                    $parameters[$index] = (int)$value;
                } elseif ($type->getName() === 'float' && is_numeric($value)) {
                    $parameters[$index] = (float)$value;
                }
            }
        } catch (\ReflectionException) {
            // 忽略反射失败,按原样传递
        }
        return $parameters;
    }
}
