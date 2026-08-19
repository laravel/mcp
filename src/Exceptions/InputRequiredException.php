<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use Exception;
use Illuminate\Support\Arr;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class InputRequiredException extends Exception
{
    /**
     * @param  array<string, array{method: string, params: array<string, mixed>}>  $inputRequests
     * @param  array<string, mixed>  $inputResponses
     */
    public function __construct(protected array $inputRequests, protected array $inputResponses = [])
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

    public function toJsonRpcResponse(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, [
            'resultType' => 'input_required',
            'inputRequests' => Arr::map(
                $this->inputRequests,
                fn (array $inputRequest): array => [...$inputRequest, 'params' => $inputRequest['params'] ?: (object) []],
            ),
            'requestState' => $request->encodeRequestState($this->inputResponses),
        ]);
    }
}
