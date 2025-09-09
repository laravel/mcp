<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Exceptions;

use Exception;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class JsonRpcException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        string $message,
        int $code,
        protected mixed $requestId = null,
        protected ?array $data = null
    ) {
        parent::__construct($message, $code);
    }

    public function toJsonRpcResponse(): JsonRpcResponse
    {
        return JsonRpcResponse::error(
            id: $this->requestId,
            code: $this->getCode(),
            message: $this->getMessage(),
            data: $this->data,
        );
    }
}
