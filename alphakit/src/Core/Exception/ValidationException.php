<?php

declare(strict_types=1);

namespace AlphaKit\Core\Exception;

class ValidationException extends HttpException
{
    /** @param array<string, array<string>> $errors 字段 => 错误列表 */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(422, '参数校验失败');
    }
}
