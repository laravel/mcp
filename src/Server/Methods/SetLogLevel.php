<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use ValueError;

class SetLogLevel implements Method
{
    public function __construct(protected LoggingManager $loggingManager)
    {
        //
    }

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        if (! $context->hasCapability(Server::CAPABILITY_LOGGING)) {
            throw new JsonRpcException(
                'Logging capability is not enabled on this server.',
                -32601,
                $request->id,
            );
        }

        $levelString = $request->get('level');

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
            $validLevels = implode(', ', array_column(LogLevel::cases(), 'value'));

            throw new JsonRpcException(
                "Invalid log level [{$levelString}]. Must be one of: {$validLevels}.",
                -32602,
                $request->id,
            );
        }

        $this->loggingManager->setLevel($level);

        return JsonRpcResponse::result($request->id, []);
    }
}
