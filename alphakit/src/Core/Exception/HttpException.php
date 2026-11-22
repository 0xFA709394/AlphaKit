<?php

declare(strict_types=1);

namespace AlphaKit\Core\Exception;

/**
 * HTTP 异常:携带状态码,由异常处理器转换为对应响应
 */
class HttpException extends \RuntimeException
{
    public function __construct(int $status, string $message = '')
    {
        $message = $message !== '' ? $message : (\AlphaKit\Core\Response::STATUS_TEXTS[$status] ?? 'HTTP Error');
        parent::__construct($message, $status);
    }
}
