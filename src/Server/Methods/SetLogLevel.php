<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use ValueError;

class SetLogLevel implements Method
{
    public function __construct(
        protected LoggingManager $loggingManager,
    ) {
        //
    }

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $levelString = $request->params['level'] ?? null;

        if (! is_string($levelString)) {
            throw new JsonRpcException(
                'Invalid Request: The [level] parameter is required and must be a string.',
                -32602,
                $request->id,
            );
        }

        try {
            $level = LogLevel::fromString($levelString);
        } catch (ValueError) {
            throw new JsonRpcException(
                "Invalid log level [{$levelString}]. Must be one of: emergency, alert, critical, error, warning, notice, info, debug.",
                -32602,
                $request->id,
            );
        }

        $this->loggingManager->setLevel($level);

        return JsonRpcResponse::result($request->id, []);
    }
}
