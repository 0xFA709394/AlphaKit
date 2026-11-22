<?php

declare(strict_types=1);

namespace App\Contracts;

interface SlugifierInterface
{
    public function make(string $title): string;
}
