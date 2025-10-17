<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\StructuredContent;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\ValidationMessages;

class CallTool implements Errable, Method
{
    use InteractsWithResponses;

    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $request->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        try {
            // @phpstan-ignore-next-line
            $response = Container::getInstance()->call([$tool, 'handle']);
        } catch (ValidationException $validationException) {
            $response = Response::error(ValidationMessages::from($validationException));
        }

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    /**
     * @return callable(Collection<int, Response>): array{content: array<int, array<string, mixed>>, isError: bool, ?structuredContent: array<string, mixed>}
     */
    protected function serializable(Tool $tool): callable
    {
        return function (Collection $responses) use ($tool): array {
            $groups = $responses->groupBy(fn (Response $response): string => $response->content() instanceof StructuredContent ? 'structuredContent' : 'content');

            $content = $groups
                ->get('content')
                ?->map(fn (Response $response): array => $response->content()->toTool($tool));

            $structuredContent = $groups
                ->get('structuredContent')
                ?->map(fn (Response $response): array => $response->content()->toTool($tool))
                ->collapse();

            if ($structuredContent?->isNotEmpty()) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $structuredContent->toJson(),
                        ],
                    ],
                    'isError' => $responses->contains(fn (Response $response): bool => $response->isError()),
                    'structuredContent' => $structuredContent->all(),
                ];
            }

            return [
                'content' => $content?->all(),
                'isError' => $responses->contains(fn (Response $response): bool => $response->isError()),
            ];
        };
    }
}
