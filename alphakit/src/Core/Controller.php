<?php

declare(strict_types=1);

namespace AlphaKit\Core;

/**
 * 控制器基类
 * 提供常用快捷方法;控制器通过容器解析,支持构造函数注入
 */
abstract class Controller
{
    /** JSON 成功响应 */
    protected function ok($data = null, string $message = 'ok'): Response
    {
        return Response::ok($data, $message);
    }

    /** JSON 失败响应 */
    protected function fail(string $message = 'error', int $code = 1, int $status = 400): Response
    {
        return Response::fail($message, $code, $status);
    }

    /**
     * 校验请求参数,失败抛 ValidationException(422)
     *
     * @return array 通过校验的数据
     */
    protected function validate(Request $request, array $rules): array
    {
        $validator = validator($request->all(), $rules);
        if ($validator->fails()) {
            throw new Exception\ValidationException($validator->errors());
        }
        return $validator->validated();
    }

    /**
     * 渲染视图
     * (命名避开 view —— 该名字常被用作动作方法,与基类签名冲突会 fatal)
     */
    protected function render(string $template, array $data = []): Response
    {
        return view($template, $data);
    }
}
