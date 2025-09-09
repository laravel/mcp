<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Exceptions;

use Exception;
use Laravel\Mcp\Server\Transport\JsonRpcProtocolError;

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

    /**
     * @return array<string, mixed>
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
