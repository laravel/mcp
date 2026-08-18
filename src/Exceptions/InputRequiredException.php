<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use Exception;
use Laravel\Mcp\Transport\JsonRpcResponse;

class InputRequiredException extends Exception
{
    /**
     * @param  array<string, array{method: string, params: array<string, mixed>}>  $inputRequests
     */
    public function __construct(protected array $inputRequests)
    {
        parent::__construct('Additional input is required.');
    }

    /**
     * @return array<string, array{method: string, params: array<string, mixed>}>
     */
    public function inputRequests(): array
    {
        return $this->inputRequests;
    }

    public function toJsonRpcResponse(int|string $id): JsonRpcResponse
    {
        return JsonRpcResponse::result($id, [
            'resultType' => 'input_required',
            'inputRequests' => $this->inputRequests,
        ]);
    }
}
