<?php

namespace Laravel\Mcp\Transport;

class JsonRpcResponse
{
    public function __construct(
        public int $id,
        public array $result,
    ) {
    }

    public static function create(int $id, array $result): JsonRpcResponse
    {
        return new static(
            id: $id,
            result: $result,
        );
    }

    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'result' => empty($this->result) ? (object) [] : $this->result,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
