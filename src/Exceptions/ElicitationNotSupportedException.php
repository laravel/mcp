<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

class ElicitationNotSupportedException extends CapabilityNotSupportedException
{
    public function __construct()
    {
        parent::__construct('The client does not support form elicitation.');
    }
}
