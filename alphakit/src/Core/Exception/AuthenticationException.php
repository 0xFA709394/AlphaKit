<?php

declare(strict_types=1);

namespace AlphaKit\Core\Exception;

class AuthenticationException extends HttpException
{
    public function __construct(string $message = '未认证')
    {
        parent::__construct(401, $message);
    }
}
