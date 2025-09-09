<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements  Arrayable<string, mixed>
 */
class JsonRpcResponse implements Arrayable
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(protected array $content = []) {}

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $result
     */
    public static function result(int $id, array|Arrayable $result): static
    {
        if ($result instanceof Arrayable) {
            $result = $result->toArray();
        }

        return new static([
            'id' => $id,
            'result' => count($result) === 0 ? (object) [] : $result,
        ]);
    }

    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $params
     */
    public static function notification(string $method, array|Arrayable $params): static
    {
        if ($params instanceof Arrayable) {
            $params = $params->toArray();
        }

        return new static([
            'method' => $method,
            'params' => $params,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function error(mixed $id, int $code, string $message, ?array $data = null): static
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return new static([
            'id' => $id,
            'error' => $error,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            ...$this->content,
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '';
    }
}
