<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Illuminate\Contracts\Support\Arrayable;

class JsonRpcResponse
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public int $id,
        /** @var array<string, mixed> $result */
        public array $result,
    ) {}

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $result
     */
    public static function create(int $id, array|Arrayable $result): JsonRpcResponse
    {
        return new static(
            id: $id,
            result: is_array($result) ? $result : $result->toArray(),
        );
    }

    /**
     * @return array<string, mixed>
     */
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
