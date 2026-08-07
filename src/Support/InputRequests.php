<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

class InputRequests
{
    public const CAPABILITIES = [
        'elicitation/create' => 'elicitation',
        'sampling/createMessage' => 'sampling',
        'roots/list' => 'roots',
    ];
}
