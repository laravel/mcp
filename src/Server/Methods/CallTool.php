<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Support\MirroredParameters;
use Laravel\Mcp\Transport\HeaderValue;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Symfony\Component\HttpFoundation\HeaderBag;

class CallTool implements Errable, Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $request->validate([
            'name' => ['nullable', 'string'],
        ]);

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
                fn (): never => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        if ($context->headers instanceof HeaderBag) {
            $this->validateMirroredHeaders($tool, $request, $context->headers);
        }

        return (new ToolInvoker)->invoke($tool, $request);
    }

    /**
     * @throws JsonRpcException
     */
    protected function validateMirroredHeaders(Tool $tool, JsonRpcRequest $request, HeaderBag $headers): void
    {
        $arguments = $request->get('arguments');
        $arguments = is_array($arguments) ? $arguments : [];

        foreach (MirroredParameters::fromSchema($tool->toArray()['inputSchema'] ?? [])->all() as $parameter) {
            $header = trim((string) $headers->get($parameter->header()), " \t");
            $value = $parameter->raw($arguments);

            $mismatch = match (true) {
                $value === null && $header === '' => null,
                $value === null => "The [{$parameter->header()}] header is present but the request body has no [{$parameter->path}] argument.",
                $header === '' => "The [{$parameter->header()}] header is required.",
                ! HeaderValue::isSafe($header) => "The [{$parameter->header()}] header value contains invalid characters.",
                $parameter->stringify($value) === null => "The [{$parameter->path}] argument is not a valid [{$parameter->type->value}].",
                ! $parameter->matches($header, $value) => "The [{$parameter->header()}] header value does not match the request body value at [{$parameter->path}].",
                default => null,
            };

            if ($mismatch !== null) {
                throw new JsonRpcException("Header mismatch: {$mismatch}", ErrorCode::HEADER_MISMATCH->value, $request->id);
            }
        }
    }
}
