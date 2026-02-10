<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Contracts\ClientMethod;

class Initialize implements ClientMethod
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function handle(ClientContext $context, array $params = []): array
    {
        $response = $context->sendRequest('initialize', [
            'protocolVersion' => $context->protocolVersion,
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => $context->clientName,
                'version' => '1.0.0',
            ],
        ]);

        $context->notify('notifications/initialized');

        return $response['result'] ?? [];
    }
}
