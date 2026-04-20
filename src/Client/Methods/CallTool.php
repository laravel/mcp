<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Contracts\ClientMethod;

class CallTool implements ClientMethod
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function handle(ClientContext $context, array $params = []): array
    {
        $response = $context->sendRequest('tools/call', $params);

        return $response['result'] ?? [];
    }
}
