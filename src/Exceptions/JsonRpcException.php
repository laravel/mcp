<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use Exception;
use Laravel\Mcp\Transport\JsonRpcResponse;

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
        $id = is_string($this->requestId) || is_int($this->requestId)
            ? $this->requestId
            : null;

        return JsonRpcResponse::error(
            id: $id,
            code: $this->getCode(),
            message: $this->getMessage(),
            data: $this->data,
        );
    }
}
