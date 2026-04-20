<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Http\Client\Factory;
use Laravel\Mcp\Client\Exceptions\ClientException;

class AuthServerDiscovery
{
    protected Factory $http;

    public function __construct(?Factory $httpFactory = null)
    {
        $this->http = $httpFactory ?? new Factory;
    }

    /**
     * @return array{token_endpoint: string, authorization_endpoint?: string}
     */
    public function discover(string $wwwAuthenticate): array
    {
        $resourceMetadataUrl = $this->parseResourceMetadataUrl($wwwAuthenticate);

        $authServerUrl = $this->fetchAuthorizationServerUrl($resourceMetadataUrl);

        return $this->fetchAuthServerMetadata($authServerUrl);
    }

    public function parseResourceMetadataUrl(string $wwwAuthenticate): string
    {
        if (preg_match('/resource_metadata="([^"]+)"/', $wwwAuthenticate, $matches) === 1) {
            return $matches[1];
        }

        throw new ClientException('WWW-Authenticate header does not contain resource_metadata URL.');
    }

    protected function fetchAuthorizationServerUrl(string $resourceMetadataUrl): string
    {
        $response = $this->http->get($resourceMetadataUrl);

        if (! $response->successful()) {
            throw new ClientException("Failed to fetch Protected Resource Metadata from [{$resourceMetadataUrl}].");
        }

        /** @var array{authorization_servers?: array<int, string>} $data */
        $data = $response->json();

        $servers = $data['authorization_servers'] ?? [];

        if ($servers === []) {
            throw new ClientException('Protected Resource Metadata does not contain authorization_servers.');
        }

        return $servers[0];
    }

    /**
     * @return array{token_endpoint: string, authorization_endpoint?: string}
     */
    protected function fetchAuthServerMetadata(string $authServerUrl): array
    {
        $metadataUrl = $this->buildMetadataUrl($authServerUrl);

        $response = $this->http->get($metadataUrl);

        if (! $response->successful()) {
            throw new ClientException("Failed to fetch OAuth Authorization Server Metadata from [{$metadataUrl}].");
        }

        /** @var array{token_endpoint?: string, authorization_endpoint?: string} $data */
        $data = $response->json();

        if (! isset($data['token_endpoint'])) {
            throw new ClientException('OAuth Authorization Server Metadata does not contain token_endpoint.');
        }

        $result = ['token_endpoint' => $data['token_endpoint']];

        if (isset($data['authorization_endpoint'])) {
            $result['authorization_endpoint'] = $data['authorization_endpoint'];
        }

        return $result;
    }

    protected function buildMetadataUrl(string $authServerUrl): string
    {
        $parsed = parse_url($authServerUrl);

        $scheme = ($parsed['scheme'] ?? 'https').'://';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = $parsed['path'] ?? '';

        return $scheme.$host.$port.'/.well-known/oauth-authorization-server'.$path;
    }
}
