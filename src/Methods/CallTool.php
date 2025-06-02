<?php

namespace Laravel\Mcp\Methods;

use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Tools\ToolNotification;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Traversable;

class CallTool implements Method
{
    /** @return JsonRpcResponse|Traversable<JsonRpcResponse> */
    public function handle(JsonRpcMessage $message, SessionContext $context)
    {
        $tool = new $context->tools[$message->params['name']]();

        try {
            $result = $tool->call($message->params['arguments']);
        } catch (ValidationException $e) {
            return JsonRpcResponse::create(
                $message->id,
                (new ToolResponse($e->getMessage(), true))->toArray()
            );
        }

        if (! $result instanceof Traversable) {
            return JsonRpcResponse::create(
                $message->id,
                $result->toArray()
            );
        }

        return (function () use ($result, $message) {
            try {
                foreach ($result as $response) {
                    if ($response instanceof ToolNotification) {
                        yield JsonRpcNotification::create(
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
            } catch (ValidationException $e) {
                yield JsonRpcResponse::create(
                    $message->id,
                    (new ToolResponse($e->getMessage(), true))->toArray()
                );
            }
        })();
    }
}
