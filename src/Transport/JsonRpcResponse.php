<?php

namespace Laravel\Mcp\Transport;

class JsonRpcResponse
{
    public function __construct(
        public string $jsonrpc = '2.0',
        public string $id,
        public array $result,
    ) {
    }

    public static function create($id, array $result): JsonRpcResponse
    {
        return new static(
            jsonrpc: '2.0',
            id: $id,
            result: $result,
        );
    }

    public function toArray(): array
    {
        return [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'result' => $this->result,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
