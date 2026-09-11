<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use Exception;
use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Request;
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
                ErrorCode::INTERNAL_ERROR->value,
                $request->id,
            );
        }

        $this->assertClientSupportsEveryInputRequest($request);

        return JsonRpcResponse::result($request->id, [
            'resultType' => 'input_required',
            'inputRequests' => (object) Arr::map(
                $this->inputRequests,
                fn (array $inputRequest): array => [
                    ...$inputRequest,
                    'params' => ($inputRequest['params'] ?? []) === [] ? (object) [] : $inputRequest['params'],
                ],
            ),
            'requestState' => $request->encodeRequestState(
                $this->inputResponses,
                $this->state,
                array_map(strval(...), array_keys($this->inputRequests)),
            ),
        ]);
    }

    /**
     * @throws JsonRpcException
     */
    protected function assertClientSupportsEveryInputRequest(JsonRpcRequest $request): void
    {
        $client = new Request(meta: $request->meta());
        $missing = [];

        foreach ($this->inputRequests as $inputRequest) {
            $capability = static::requiredCapability($inputRequest);

            if (! is_null($capability) && ! $client->clientSupports($capability)) {
                data_set($missing, $capability, (object) []);
            }
        }

        if ($missing === []) {
            return;
        }

        throw new JsonRpcException(
            'The client did not declare the capabilities this request requires.',
            ErrorCode::MISSING_REQUIRED_CLIENT_CAPABILITY->value,
            $request->id,
            ['requiredCapabilities' => $missing],
        );
    }

    /**
     * @param  array{method: string, params?: array<string, mixed>}  $inputRequest
     */
    protected static function requiredCapability(array $inputRequest): ?string
    {
        $params = $inputRequest['params'] ?? [];

        return match ($inputRequest['method'] ?? null) {
            'elicitation/create' => ($params['mode'] ?? 'form') === 'url' ? 'elicitation.url' : 'elicitation.form',
            'sampling/createMessage' => isset($params['tools']) || isset($params['toolChoice']) ? 'sampling.tools' : 'sampling',
            'roots/list' => 'roots',
            default => null,
        };
    }
}
