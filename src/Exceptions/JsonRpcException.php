<?php

namespace Laravel\Mcp\Exceptions;

use Exception;

class JsonRpcException extends Exception
{
    protected mixed $requestId;

    public function __construct(string $message, int $code, mixed $requestId = null)
    {
        parent::__construct($message, $code);
        $this->requestId = $requestId;
    }

    public function toJsonRpcError(): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $this->getCode(),
                'message' => $this->getMessage(),
            ],
            'id' => $this->requestId,
        ];
    }
}
