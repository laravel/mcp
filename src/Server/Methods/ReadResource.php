<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Illuminate\Support\ItemNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class ReadResource implements Method
{
    public function handle(JsonRpcRequest $jsonRpcRequest, ServerContext $context): JsonRpcResponse
    {
        if (is_null($jsonRpcRequest->get('uri'))) {
            throw new InvalidArgumentException('Missing required parameter: uri');
        }

        $resource = $context->resources(
            $jsonRpcRequest->toRequest(),
        )->first(fn (Resource $resource): bool => $resource->uri() === $jsonRpcRequest->get('uri'));

        if (is_null($resource)) {
            throw new ItemNotFoundException('Resource not found');
        }

        return JsonRpcResponse::result(
            $jsonRpcRequest->id,
            $resource->handle()->toArray(),
        );
    }
}
