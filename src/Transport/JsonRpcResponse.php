<?php

namespace Laravel\Mcp\Transport;

class JsonRpcResponse
{
    /**
     * Create a new JSON-RPC response.
     */
    public function __construct(
        public int $id,
        public array $result,
    ) {
    }

    /**
     * Create a new JSON-RPC response.
     */
    public static function create(int $id, array $result): JsonRpcResponse
    {
        return new static(
            id: $id,
            result: $result,
        );
    }

    /**
     * Convert the response to an array.
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'result' => empty($this->result) ? (object) [] : $this->result,
        ];
    }

    /**
     * Convert the response to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
