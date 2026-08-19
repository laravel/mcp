<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use RuntimeException;

class SamplingNotSupportedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The client does not support sampling.');
    }
}
