<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Exceptions\DiscoveryException;
use Throwable;

class AuthServerDiscovery
{
    public function discoverProtectedResource(string $mcpUrl, ?WwwAuthenticateChallenge $challenge = null): ProtectedResourceMetadata
    {
        return $this->discover(
            $this->protectedResourceCandidates($mcpUrl, $challenge),
            fn (string $url, Response $response): ProtectedResourceMetadata => $this->parseProtectedResource($url, $response),
            "Unable to discover protected resource metadata for [{$mcpUrl}].",
        );
    }

    public function discoverAuthServer(string $issuer): AuthServerMetadata
    {
        return $this->discover(
            $this->authServerCandidates($issuer),
            fn (string $url, Response $response): AuthServerMetadata => $this->parseAuthServer($url, $response),
            "Unable to discover authorization server metadata for [{$issuer}].",
        );
    }

    /**
     * @template T
     *
     * @param  array<int, string>  $candidates
     * @param  Closure(string, Response): T  $parser
     * @return T
     */
    protected function discover(array $candidates, Closure $parser, string $exhaustedMessage): mixed
    {
        foreach ($candidates as $candidate) {
            $this->assertHttps($candidate);

            $response = $this->safelyGet($candidate);

            if ($response->status() === 404) {
                continue;
            }

            if (! $response->successful()) {
                throw new DiscoveryException("Discovery of [{$candidate}] failed with HTTP status [{$response->status()}].");
            }

            return $parser($candidate, $response);
        }

        throw new DiscoveryException($exhaustedMessage);
    }

    /**
     * @return array<int, string>
     */
    protected function protectedResourceCandidates(string $mcpUrl, ?WwwAuthenticateChallenge $challenge): array
    {
        if ($challenge?->resourceMetadata !== null) {
            $this->assertSameOrigin($mcpUrl, $challenge->resourceMetadata);

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

        if ($path === '') {
            return [
                $issuerNoSlash.'/.well-known/oauth-authorization-server',
                $issuerNoSlash.'/.well-known/openid-configuration',
            ];
        }

        return [
            $origin.'/.well-known/oauth-authorization-server'.$path,
            $origin.'/.well-known/openid-configuration'.$path,
            $issuerNoSlash.'/.well-known/openid-configuration',
        ];
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

    protected function assertSameOrigin(string $resourceUrl, string $metadataUrl): void
    {
        $resourceOrigin = strtolower($this->parseOriginAndPath($resourceUrl)['origin']);
        $metadataOrigin = strtolower($this->parseOriginAndPath($metadataUrl)['origin']);

        if ($resourceOrigin !== $metadataOrigin) {
            throw new DiscoveryException("Protected resource metadata URL [{$metadataUrl}] must share the origin of [{$resourceUrl}].");
        }
    }

    protected function assertHttps(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new DiscoveryException("Invalid discovery URL [{$url}].");
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme === 'https') {
            return;
        }

        if ($scheme === 'http' && $this->isLoopbackHost($parts['host'])) {
            return;
        }

        throw new DiscoveryException("Discovery URL [{$url}] must use HTTPS.");
    }

    protected function isLoopbackHost(string $host): bool
    {
        $normalized = strtolower(trim($host, '[]'));

        return in_array($normalized, ['localhost', '127.0.0.1', '::1'], true);
    }

    protected function safelyGet(string $url): Response
    {
        try {
            return Http::acceptJson()->get($url);
        } catch (Throwable $throwable) {
            throw new DiscoveryException("Discovery request to [{$url}] failed: {$throwable->getMessage()}.", $throwable->getCode(), $throwable);
        }
    }

    protected function parseProtectedResource(string $url, Response $response): ProtectedResourceMetadata
    {
        $data = $this->decodeJson($url, $response);

        $authorizationServers = Arr::get($data, 'authorization_servers');

        if (! is_array($authorizationServers) || $authorizationServers === []) {
            throw new DiscoveryException("Protected resource metadata at [{$url}] is missing [authorization_servers].");
        }

        return new ProtectedResourceMetadata(
            resource: (string) Arr::get($data, 'resource', ''),
            authorizationServers: $this->toStringList($authorizationServers),
            scopesSupported: $this->toStringList(Arr::get($data, 'scopes_supported', [])),
        );
    }

    protected function parseAuthServer(string $url, Response $response): AuthServerMetadata
    {
        $data = $this->decodeJson($url, $response);

        $tokenEndpoint = Arr::get($data, 'token_endpoint');

        if (! is_string($tokenEndpoint) || $tokenEndpoint === '') {
            throw new DiscoveryException("Authorization server metadata at [{$url}] is missing [token_endpoint].");
        }

        $this->assertHttps($tokenEndpoint);

        $authorizationEndpoint = filled($data['authorization_endpoint'] ?? null) && is_string($data['authorization_endpoint'])
            ? $data['authorization_endpoint']
            : null;

        if ($authorizationEndpoint !== null) {
            $this->assertHttps($authorizationEndpoint);
        }

        return new AuthServerMetadata(
            issuer: (string) Arr::get($data, 'issuer', ''),
            tokenEndpoint: $tokenEndpoint,
            authorizationEndpoint: $authorizationEndpoint,
            grantTypesSupported: $this->toStringList(Arr::get($data, 'grant_types_supported', [])),
            codeChallengeMethodsSupported: $this->toStringList(Arr::get($data, 'code_challenge_methods_supported', [])),
            registrationEndpoint: filled($data['registration_endpoint'] ?? null) ? (string) $data['registration_endpoint'] : null,
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

        return collect($value)
            ->filter(fn ($item): bool => is_string($item) || is_int($item) || is_float($item))
            ->map(fn ($item): string => (string) $item)
            ->values()
            ->all();
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
