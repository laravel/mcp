<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Illuminate\Support\Arr;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\Concerns\InteractsWithOAuthEndpoints;

class AuthServerDiscovery
{
    use InteractsWithOAuthEndpoints;

    public function discover(string $resourceUrl, ?string $resourceMetadataUrl = null): DiscoveryResult
    {
        $metadataUrl = $resourceMetadataUrl ?? $this->wellKnown($resourceUrl, 'oauth-protected-resource');

        $this->requireFetchable($metadataUrl, $resourceUrl);

        $resourceMetadata = $this->fetchResourceMetadata($metadataUrl, explicit: $resourceMetadataUrl !== null);

        $this->requireResourceMatches($resourceMetadata, $resourceUrl);

        $issuer = $this->issuerFrom($resourceMetadata) ?? $this->origin($resourceUrl);

        $this->requireSecure($issuer);
        $this->requireNotInternal($issuer, $resourceUrl);

        $serverMetadata = $this->fetchMetadata($issuer);

        if (! hash_equals($issuer, $serverMetadata->issuer)) {
            throw new OAuthException("Authorization server issuer [{$serverMetadata->issuer}] did not match the expected issuer [{$issuer}].");
        }

        $this->requireSecure($serverMetadata->authorizationEndpoint);
        $this->requireSecure($serverMetadata->tokenEndpoint);
        $this->requireNotInternal($serverMetadata->authorizationEndpoint, $resourceUrl);
        $this->requireNotInternal($serverMetadata->tokenEndpoint, $resourceUrl);

        if ($serverMetadata->registrationEndpoint !== null) {
            $this->requireSecure($serverMetadata->registrationEndpoint);
            $this->requireNotInternal($serverMetadata->registrationEndpoint, $resourceUrl);
        }

        $scopesSupported = array_values(array_map(strval(...), (array) ($resourceMetadata['scopes_supported'] ?? [])));

        return new DiscoveryResult($serverMetadata, $scopesSupported);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchResourceMetadata(string $metadataUrl, bool $explicit = false): array
    {
        $response = $this->oAuthRequest()->get($metadataUrl);

        if (! $response->successful()) {
            if ($explicit) {
                throw new OAuthException("Protected resource metadata request to [{$metadataUrl}] failed with status [{$response->status()}].");
            }

            return [];
        }

        $data = $response->json();

        if (is_array($data)) {
            return $data;
        }

        if ($explicit) {
            throw new OAuthException("Protected resource metadata at [{$metadataUrl}] did not return a valid JSON object.");
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $resourceMetadata
     */
    protected function requireResourceMatches(array $resourceMetadata, string $resourceUrl): void
    {
        $resource = Arr::get($resourceMetadata, 'resource');

        if (is_string($resource) && ! hash_equals($resourceUrl, $resource)) {
            throw new OAuthException("Protected resource metadata resource [{$resource}] did not match the expected resource [{$resourceUrl}].");
        }
    }

    /**
     * @param  array<string, mixed>  $resourceMetadata
     */
    protected function issuerFrom(array $resourceMetadata): ?string
    {
        $servers = Arr::get($resourceMetadata, 'authorization_servers');

        if (is_array($servers) && $servers !== []) {
            return (string) $servers[0];
        }

        return null;
    }

    protected function fetchMetadata(string $issuer): AuthServerMetadata
    {
        foreach ($this->metadataUrls($issuer) as $metadataUrl) {
            $response = $this->oAuthRequest()->get($metadataUrl);

            if (! $response->successful()) {
                continue;
            }

            $metadata = $response->json();

            if (is_array($metadata)) {
                return AuthServerMetadata::fromArray($metadata);
            }
        }

        throw new OAuthException("Unable to discover authorization server metadata from [{$issuer}].");
    }

    /**
     * @return array<int, string>
     */
    protected function metadataUrls(string $issuer): array
    {
        $parts = $this->parse($issuer);

        $origin = $this->originFromParts($parts);

        $path = $parts['path'] ?? '';

        if ($path === '') {
            return [
                $origin.'/.well-known/oauth-authorization-server',
                $origin.'/.well-known/openid-configuration',
            ];
        }

        return [
            $origin.'/.well-known/oauth-authorization-server'.$path,
            $origin.'/.well-known/openid-configuration'.$path,
            $origin.$path.'/.well-known/openid-configuration',
        ];
    }

    protected function wellKnown(string $url, string $type): string
    {
        $parts = $this->parse($url);

        $path = $parts['path'] ?? '';

        return $this->originFromParts($parts).'/.well-known/'.$type.$path;
    }

    protected function origin(string $url): string
    {
        return $this->originFromParts($this->parse($url));
    }

    protected function requireSecure(string $url): void
    {
        $parts = $this->parse($url);

        if ($parts['scheme'] === 'https') {
            return;
        }

        if ($this->isLocalhost($this->normalizedHost($parts['host']))) {
            return;
        }

        throw new OAuthException("OAuth endpoint [{$url}] must be served over HTTPS.");
    }

    protected function requireFetchable(string $url, string $resourceUrl): void
    {
        $this->requireSecure($url);
        $this->requireNotInternal($url, $resourceUrl);
    }

    protected function requireNotInternal(string $url, string $resourceUrl): void
    {
        $parts = $this->parse($url);
        $resourceParts = $this->parse($resourceUrl);
        $host = $this->normalizedHost($parts['host']);
        $resourceHost = $this->normalizedHost($resourceParts['host']);

        if ($this->isInternalHost($host) && ! ($this->isLocalhost($host) && $this->isLocalhost($resourceHost))) {
            throw new OAuthException("OAuth endpoint [{$url}] cannot use a private or internal host.");
        }
    }

    protected function isInternalHost(string $host): bool
    {
        if ($this->isLocalhost($host)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected function isLocalhost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    protected function normalizedHost(string $host): string
    {
        return strtolower(trim($host, '[]'));
    }

    /**
     * @param  array{scheme: string, host: string, port?: int, path?: string}  $parts
     */
    protected function originFromParts(array $parts): string
    {
        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @return array{scheme: string, host: string, port?: int, path?: string}
     */
    protected function parse(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new OAuthException("Unable to parse URL [{$url}] during OAuth discovery.");
        }

        /** @var array{scheme: string, host: string, port?: int, path?: string} $parts */
        return $parts;
    }
}
