<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth\Concerns;

use GuzzleHttp\ClientInterface;
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
            ->withOptions(['allow_redirects' => false, ...$this->oAuthTransportOptions()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function oAuthTransportOptions(): array
    {
        if (! defined('CURLOPT_PROTOCOLS')) {
            return [];
        }

        if (ClientInterface::MAJOR_VERSION >= 8) {
            return [
                'protocols' => ['http', 'https'],
                'progress' => fn (int $downloadTotal, int $downloaded): bool => max($downloadTotal, $downloaded) > $this->maxOAuthResponseSize,
            ];
        }

        return ['curl' => [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => $this->maxOAuthResponseSize,
        ]];
    }
}
