<?php

namespace Laravel\Mcp\Exceptions;

use Exception;
use Laravel\Mcp\Transport\JsonRpcProtocolError;

class JsonRpcException extends Exception
{
    protected mixed $requestId;
    protected ?array $data;

    public function __construct(string $message, int $code, mixed $requestId = null, ?array $data = null)
    {
        parent::__construct($message, $code);
        $this->requestId = $requestId;
        $this->data = $data;
    }

    public function getRequestId(): mixed
    {
        return $this->requestId;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

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
