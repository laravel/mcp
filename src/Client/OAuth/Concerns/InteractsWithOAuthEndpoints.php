<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait InteractsWithOAuthEndpoints
{
    protected int $maxOAuthResponseSize = 1_048_576;

    protected function oAuthRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(5)
            ->connectTimeout(2)
            ->withOptions(['allow_redirects' => false, ...$this->oAuthCurlOptions()]);
    }

    /**
     * @return array<string, array<int, int>>
     */
    protected function oAuthCurlOptions(): array
    {
        if (! defined('CURLOPT_PROTOCOLS')) {
            return [];
        }

        return ['curl' => [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => $this->maxOAuthResponseSize,
        ]];
    }
}
