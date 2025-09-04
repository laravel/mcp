<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Generator;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcNotification;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

interface Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcNotification|JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context);
}
