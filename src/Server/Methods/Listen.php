<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Listen implements Method
{
    /**
     * @return Generator<int, JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator
    {
        yield JsonRpcResponse::notification('notifications/subscriptions/acknowledged', [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id],
            'notifications' => (object) [],
        ]);

        yield JsonRpcResponse::result($request->id, [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id],
        ]);
    }
}
