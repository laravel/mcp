<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Generator;
use Laravel\Mcp\Server\Contracts\Transport\JsonRpcResponse;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

interface Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse;
}
