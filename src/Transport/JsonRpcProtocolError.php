<?php

namespace Laravel\Mcp\Transport;

class JsonRpcProtocolError
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly mixed $requestId = null,
        public readonly ?array $data = null
    ) {
    }

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

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
} 