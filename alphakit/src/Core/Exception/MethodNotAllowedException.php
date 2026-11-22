<?php

declare(strict_types=1);

namespace AlphaKit\Core\Exception;

class MethodNotAllowedException extends HttpException
{
    public function __construct(string $path, string $method)
    {
        parent::__construct(405, sprintf('Method %s not allowed for %s', $method, $path));
    }
}
