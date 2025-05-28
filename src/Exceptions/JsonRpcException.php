<?php

namespace Laravel\Mcp\Exceptions;

use Exception;

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
        $error = [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $this->requestId,
        ];
    }
}
