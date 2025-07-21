<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

class JsonRpcProtocolError
{
    /**
     * Create a new JSON-RPC protocol error response.
     */
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly mixed $requestId = null,
        public readonly ?array $data = null
    ) {}

    /**
     * Convert the error response to an array.
     */
    public function toArray(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
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

    /**
     * Convert the error response to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
