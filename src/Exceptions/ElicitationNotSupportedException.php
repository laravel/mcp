<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use RuntimeException;

class ElicitationNotSupportedException extends RuntimeException
{
    public function __construct(string $mode)
    {
        parent::__construct("The client does not support {$mode} elicitation.");
    }
}
