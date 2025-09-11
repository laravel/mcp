<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class CallTool implements Method
{
    use InteractsWithResponses;

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        try {
            $tool = $context->tools()
                ->firstOrFail(fn ($tool): bool => $tool->name() === $request->params['name']);
        } catch (ItemNotFoundException) {
            return JsonRpcResponse::result(
                $request->id,
                Response::error('Tool ['.$request->params['name'].'] not found.')->content()->toArray(),
            );
        }

        try {
            $response = Container::getInstance()->call([$tool, 'handle'], [
                'request' => new Request(
                    $request->params['arguments'],
                ),
            ]);
        } catch (ValidationException $validationException) {
            $response = Response::error(ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable())
            : $this->toJsonRpcResponse($request, $response, $this->serializable());
    }

    /**
     * @return callable(Collection<int, Response>): array{content: array<int, array<string, mixed>>, isError: bool}
     */
    protected function serializable(): callable
    {
        return fn (Collection $responses): array => [
            'content' => $responses->map(fn (Response $response): array => $response->content()->toArray())->all(),
            'isError' => $responses->contains(fn (Response $response): bool => $response->isError()),
        ];
    }
}
