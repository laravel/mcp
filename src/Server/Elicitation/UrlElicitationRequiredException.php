<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation;

use Laravel\Mcp\Server\Exceptions\JsonRpcException;

class UrlElicitationRequiredException extends JsonRpcException
{
    /**
     * @param  array<int, array<string, mixed>>  $elicitations
     */
    public function __construct(string $message, array $elicitations)
    {
        parent::__construct($message, -32042, data: [
            'elicitations' => $elicitations,
        ]);
    }
}
