<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

interface Method
{
    /**
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse;
}
