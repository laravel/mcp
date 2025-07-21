<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Exceptions;

use Exception;
use Laravel\Mcp\Server\Transport\JsonRpcProtocolError;

class JsonRpcException extends Exception
{
    protected mixed $requestId;

    protected ?array $data;

    /**
     * Create a new JSON-RPC exception.
     */
    public function __construct(string $message, int $code, mixed $requestId = null, ?array $data = null)
    {
        parent::__construct($message, $code);
        $this->requestId = $requestId;
        $this->data = $data;
    }

    /**
     * Get the request ID.
     */
    public function getRequestId(): mixed
    {
        return $this->requestId;
    }

    /**
     * Get the exception data.
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Convert the exception to a JSON-RPC protocol error.
     */
    public function toJsonRpcError(): array
    {
        return (new JsonRpcProtocolError(
            code: $this->getCode(),
            message: $this->getMessage(),
            requestId: $this->requestId,
            data: $this->data,
        ))->toArray();
    }
}
