<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait InteractsWithOAuthEndpoints
{
    /**
     * @param  array<string, mixed>  $options
     */
    protected function oAuthRequest(array $options = []): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(5)
            ->connectTimeout(2)
            ->withOptions([...$options, 'allow_redirects' => false]);
    }
}
