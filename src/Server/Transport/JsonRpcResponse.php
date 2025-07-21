<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Illuminate\Contracts\Support\Arrayable;

class JsonRpcResponse
{
    /**
     * @param  array<mixed>  $result
     */
    public function __construct(
        public int $id,
        public array $result,
    ) {}

    /**
     * Create a new JSON-RPC response.
     */
    public static function create(int $id, array|Arrayable $result): JsonRpcResponse
    {
        return new static(
            id: $id,
            result: is_array($result) ? $result : $result->toArray(),
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
