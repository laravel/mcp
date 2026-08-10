<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Subscription;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Listen implements Method
{
    /**
     * @return Generator<JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator
    {
        $subscription = Subscription::from($request->id, $request->get('notifications'), $context);

        yield $this->stamped(JsonRpcResponse::notification(
            'notifications/subscriptions/acknowledged',
            ['notifications' => $subscription->notifications() ?: (object) []],
        ), $subscription);

        foreach ($context->notificationsFor($subscription) as $notification) {
            $frame = $this->toNotification($notification, $subscription);

            if (! $frame instanceof JsonRpcResponse) {
                continue;
            }

            yield $this->stamped($frame, $subscription);
        }

        yield JsonRpcResponse::result($request->id, [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $subscription->id],
        ]);
    }

    protected function toNotification(mixed $notification, Subscription $subscription): ?JsonRpcResponse
    {
        if (! $notification instanceof Response || ! $notification->isNotification()) {
            return null;
        }

        /** @var Notification $content */
        $content = $notification->content();
        $frame = $content->toArray();

        $method = is_string($frame['method'] ?? null) ? $frame['method'] : '';
        $params = is_array($frame['params'] ?? null) ? $frame['params'] : [];
        $uri = is_string($params['uri'] ?? null) ? $params['uri'] : null;

        return $subscription->wants($method, $uri)
            ? JsonRpcResponse::notification($method, $params)
            : null;
    }

    protected function stamped(JsonRpcResponse $response, Subscription $subscription): JsonRpcResponse
    {
        $frame = $response->toArray();
        $method = is_string($frame['method'] ?? null) ? $frame['method'] : '';
        $params = (array) ($frame['params'] ?? []);

        $params['_meta'] = [
            ...is_array($params['_meta'] ?? null) ? $params['_meta'] : [],
            MetaKey::SUBSCRIPTION_ID->value => $subscription->id,
        ];

        return JsonRpcResponse::notification($method, $params);
    }
}
