<?php

namespace Laravel\Mcp\Methods;

use InvalidArgumentException;
use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\Resources\ResourceResult;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ReadResource implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        if (empty($request->params['uri'])) {
            throw new InvalidArgumentException('Missing required parameter: uri');
        }

        $resource = collect($context->resources)
            ->map(fn ($resource) => is_string($resource) ? new $resource : $resource)
            ->firstOrFail(fn ($resource) => $resource->uri() === $request->params['uri']);

        return new JsonRpcResponse(
            $request->id,
            (new ResourceResult($resource))->toArray(),
        );
    }
}
