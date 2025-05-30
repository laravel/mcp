<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Tools\ToolNotification;
use Laravel\Mcp\Transport\JsonRpcNotifcation;
use Traversable;

class CallTool implements Method
{
    /** @return JsonRpcResponse|Traversable<JsonRpcResponse> */
    public function handle(JsonRpcMessage $message, SessionContext $context)
    {
        $tool = new $context->tools[$message->params['name']]();

        $result = $tool->call($message->params['arguments']);

        if (! $result instanceof Traversable) {
            return JsonRpcResponse::create(
                $message->id,
                $result->toArray()
            );
        }

        return (function () use ($result, $message) {
            foreach ($result as $response) {
                if ($response instanceof ToolNotification) {
                    yield JsonRpcNotifcation::create(
                        $response->getMethod(),
                        $response->toArray()
                    );
                    continue;
                }

                yield JsonRpcResponse::create(
                    $message->id,
                    $response->toArray()
                );
            }
        })();
    }
}
