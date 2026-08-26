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
     * @var array<int, string>
     */
    protected const SUPPORTED_METHODS = ['tools/call', 'prompts/get', 'resources/read'];

    /**
     * @param  array<array-key, array{method: string, params: array<string, mixed>}>  $inputRequests
     * @param  array<array-key, mixed>  $inputResponses
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        protected array $inputRequests,
        protected array $inputResponses = [],
        protected array $state = [],
    ) {
        parent::__construct('Additional input is required.');
    }

    /**
     * @return array<array-key, array{method: string, params: array<string, mixed>}>
     */
    public function inputRequests(): array
    {
        return $this->inputRequests;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->state;
    }

    /**
     * @throws JsonRpcException
     */
    public function toJsonRpcResponse(JsonRpcRequest $request): JsonRpcResponse
    {
        if (! in_array($request->method, static::SUPPORTED_METHODS, true)) {
            throw new JsonRpcException(
                "The [{$request->method}] method may not request additional input.",
                -32603,
                $request->id,
            );
        }

        return JsonRpcResponse::result($request->id, [
            'resultType' => 'input_required',
            'inputRequests' => (object) Arr::map(
                $this->inputRequests,
                fn (array $inputRequest): array => [...$inputRequest, 'params' => ($inputRequest['params'] ?? []) ?: (object) []],
            ),
            'requestState' => $request->encodeRequestState($this->inputResponses, $this->state),
        ]);
    }
}
