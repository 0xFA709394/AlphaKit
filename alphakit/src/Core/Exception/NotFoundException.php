<?php

declare(strict_types=1);

namespace AlphaKit\Core\Exception;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct(404, $message);
    }
}
