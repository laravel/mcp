<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

class SamplingNotSupportedException extends CapabilityNotSupportedException
{
    public function __construct()
    {
        parent::__construct('The client does not support sampling.');
    }
}
