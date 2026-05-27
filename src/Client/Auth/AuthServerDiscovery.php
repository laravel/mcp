<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Exceptions\DiscoveryException;
use Throwable;

class AuthServerDiscovery
{
    public function discoverProtectedResource(string $mcpUrl, ?WwwAuthenticateChallenge $challenge = null): ProtectedResourceMetadata
    {
        $candidates = $this->protectedResourceCandidates($mcpUrl, $challenge);

        foreach ($candidates as $candidate) {
            $this->assertHttps($candidate);

            $response = $this->safelyGet($candidate);

            if (! $response instanceof Response) {
                continue;
            }

            if ($response->status() === 404) {
                continue;
            }

            if (! $response->successful()) {
                throw new DiscoveryException("Discovery of [{$candidate}] failed with HTTP status [{$response->status()}].");
            }

            return $this->parseProtectedResource($candidate, $response);
        }

        throw new DiscoveryException("Unable to discover protected resource metadata for [{$mcpUrl}].");
    }

    public function discoverAuthServer(string $issuer): AuthServerMetadata
    {
        $candidates = $this->authServerCandidates($issuer);

        foreach ($candidates as $candidate) {
            $this->assertHttps($candidate);

            $response = $this->safelyGet($candidate);

            if (! $response instanceof Response) {
                continue;
            }

            if ($response->status() === 404) {
                continue;
            }

            if (! $response->successful()) {
                throw new DiscoveryException("Discovery of [{$candidate}] failed with HTTP status [{$response->status()}].");
            }

            return $this->parseAuthServer($candidate, $response);
        }

        throw new DiscoveryException("Unable to discover authorization server metadata for [{$issuer}].");
    }

    /**
     * @return array<int, string>
     */
    protected function protectedResourceCandidates(string $mcpUrl, ?WwwAuthenticateChallenge $challenge): array
    {
        if ($challenge?->resourceMetadata !== null) {
            return [$challenge->resourceMetadata];
        }

        ['origin' => $origin, 'path' => $path] = $this->parseOriginAndPath($mcpUrl);

        $candidates = [];

        if ($path !== '') {
            $candidates[] = $origin.'/.well-known/oauth-protected-resource'.$path;
        }

        $candidates[] = $origin.'/.well-known/oauth-protected-resource';

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int, string>
     */
    protected function authServerCandidates(string $issuer): array
    {
        ['origin' => $origin, 'path' => $path] = $this->parseOriginAndPath($issuer);
        $issuerNoSlash = rtrim($issuer, '/');

        $candidates = [];

        if ($path !== '') {
            $candidates[] = $origin.'/.well-known/oauth-authorization-server'.$path;
            $candidates[] = $origin.'/.well-known/openid-configuration'.$path;
        }

        $candidates[] = $issuerNoSlash.'/.well-known/oauth-authorization-server';
        $candidates[] = $issuerNoSlash.'/.well-known/openid-configuration';

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{origin: string, path: string}
     */
    protected function parseOriginAndPath(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new DiscoveryException("Cannot derive discovery URLs from invalid URL [{$url}].");
        }

        return [
            'origin' => $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''),
            'path' => isset($parts['path']) && $parts['path'] !== '/' ? rtrim($parts['path'], '/') : '',
        ];
    }

    protected function assertHttps(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new DiscoveryException("Invalid discovery URL [{$url}].");
        }

        if ($parts['scheme'] === 'https') {
            return;
        }

        if ($parts['scheme'] === 'http' && $this->isLoopbackHost($parts['host'])) {
            return;
        }

        throw new DiscoveryException("Discovery URL [{$url}] must use HTTPS.");
    }

    protected function isLoopbackHost(string $host): bool
    {
        $normalized = strtolower(trim($host, '[]'));

        return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
    }

    protected function safelyGet(string $url): ?Response
    {
        try {
            return Http::acceptJson()->get($url);
        } catch (ConnectionException $connectionException) {
            throw new DiscoveryException("Discovery request to [{$url}] failed: {$connectionException->getMessage()}.", $connectionException->getCode(), $connectionException);
        } catch (Throwable $throwable) {
            throw new DiscoveryException("Discovery request to [{$url}] failed: {$throwable->getMessage()}.", $throwable->getCode(), $throwable);
        }
    }

    protected function parseProtectedResource(string $url, Response $response): ProtectedResourceMetadata
    {
        $data = $this->decodeJson($url, $response);

        $authorizationServers = $data['authorization_servers'] ?? null;

        if (! is_array($authorizationServers) || $authorizationServers === []) {
            throw new DiscoveryException("Protected resource metadata at [{$url}] is missing [authorization_servers].");
        }

        return new ProtectedResourceMetadata(
            resource: (string) ($data['resource'] ?? ''),
            authorizationServers: $this->toStringList($authorizationServers),
            scopesSupported: $this->toStringList($data['scopes_supported'] ?? []),
        );
    }

    protected function parseAuthServer(string $url, Response $response): AuthServerMetadata
    {
        $data = $this->decodeJson($url, $response);

        $tokenEndpoint = $data['token_endpoint'] ?? null;

        if (! is_string($tokenEndpoint) || $tokenEndpoint === '') {
            throw new DiscoveryException("Authorization server metadata at [{$url}] is missing [token_endpoint].");
        }

        $this->assertHttps($tokenEndpoint);

        return new AuthServerMetadata(
            issuer: (string) ($data['issuer'] ?? ''),
            tokenEndpoint: $tokenEndpoint,
            authorizationEndpoint: isset($data['authorization_endpoint']) ? (string) $data['authorization_endpoint'] : null,
            grantTypesSupported: $this->toStringList($data['grant_types_supported'] ?? []),
            codeChallengeMethodsSupported: $this->toStringList($data['code_challenge_methods_supported'] ?? []),
        );
    }

    /**
     * @return array<int, string>
     */
    protected function toStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(fn ($item): string => (string) $item, $value));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(string $url, Response $response): array
    {
        try {
            $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new DiscoveryException("Discovery response from [{$url}] is not valid JSON.");
        }

        if (! is_array($data)) {
            throw new DiscoveryException("Discovery response from [{$url}] is not a JSON object.");
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
