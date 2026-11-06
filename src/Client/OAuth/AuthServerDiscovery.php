<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Closure;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Exception\MalformedUriException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\Concerns\InteractsWithOAuthEndpoints;
use Laravel\Mcp\Support\AddressGuard;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class AuthServerDiscovery
{
    use InteractsWithOAuthEndpoints;

    protected const NON_PUBLIC_IPV6_PREFIXES = AddressGuard::NON_PUBLIC_IPV6_PREFIXES;

    protected ?AddressGuard $addressGuard = null;

    public static function resolveHostsUsing(?Closure $callback): void
    {
        AddressGuard::resolveHostsUsing($callback);
    }

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
        $this->requireNotInternal($serverMetadata->authorizationEndpoint, $resourceUrl, resolve: false);
        $this->requireNotInternal($serverMetadata->tokenEndpoint, $resourceUrl);

        if ($serverMetadata->registrationEndpoint !== null) {
            $this->requireSecure($serverMetadata->registrationEndpoint);
            $this->requireNotInternal($serverMetadata->registrationEndpoint, $resourceUrl);
        }

        $scopesSupported = array_values(array_map(strval(...), (array) ($resourceMetadata['scopes_supported'] ?? [])));

        return new DiscoveryResult($serverMetadata, $scopesSupported);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function requireAdvertised(string $resourceUrl, string $resourceMetadataUrl, array $headers = []): void
    {
        $this->requireFetchable($resourceMetadataUrl, $resourceUrl);

        if ($resourceMetadataUrl === $this->wellKnown($resourceUrl, 'oauth-protected-resource')) {
            return;
        }

        $this->requireFetchable($resourceUrl, $resourceUrl);

        $response = $this->probe($resourceUrl, array_filter(
            $headers,
            static fn (string $name): bool => strcasecmp($name, 'Authorization') !== 0 && ! str_starts_with(strtolower($name), 'mcp-'),
            ARRAY_FILTER_USE_KEY,
        ));

        $advertised = WwwAuthenticateChallenge::parse($response->header('WWW-Authenticate'))->resourceMetadataUrl;

        if (! in_array($response->status(), [401, 403], true) || $advertised === null || ! hash_equals($advertised, $resourceMetadataUrl)) {
            throw new OAuthException("The protected resource metadata URL [{$resourceMetadataUrl}] was not advertised by the resource [{$resourceUrl}].");
        }
    }

    public function pin(PendingRequest $request): PendingRequest
    {
        return $this->addressGuard()->pin($request);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function probe(string $resourceUrl, array $headers): Response
    {
        try {
            return $this->pin($this->oAuthRequest())
                ->withHeaders($headers)
                ->withOptions(['allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri) use ($resourceUrl): void {
                        $this->requireFetchable((string) $uri, $resourceUrl);
                    },
                ]])
                ->accept('application/json, text/event-stream')
                ->post($resourceUrl, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'ping',
                ]);
        } catch (ConnectionException|TransferException|MalformedUriException $exception) {
            throw new OAuthException("Unable to reach the resource [{$resourceUrl}] to verify its protected resource metadata.", $exception->getCode(), previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchResourceMetadata(string $metadataUrl, bool $explicit = false): array
    {
        $response = $this->pin($this->oAuthRequest())->get($metadataUrl);

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
            $response = $this->pin($this->oAuthRequest())->get($metadataUrl);

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

        $path = rtrim($parts['path'] ?? '', '/');

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

        if ($parts['scheme'] === 'http' && $this->isLocalhost($this->normalizedHost($parts['host']))) {
            return;
        }

        throw new OAuthException("OAuth endpoint [{$url}] must be served over HTTPS.");
    }

    public function requireFetchable(string $url, string $resourceUrl): void
    {
        $this->requireSecure($url);
        $this->requireNotInternal($url, $resourceUrl);
    }

    protected function requireNotInternal(string $url, string $resourceUrl, bool $resolve = true): void
    {
        $host = $this->normalizedHost($this->parse($url)['host']);

        if (! $this->isLocalhost($host) && ! $this->isIpAddress($host)) {
            if (! $resolve || ($this->usesProxy() && ! $this->isLocalName($host))) {
                return;
            }

            if ($this->resolve($host) === []) {
                throw new OAuthException("OAuth endpoint [{$url}] cannot use a private, internal, or unresolvable host.");
            }
        }

        if (! $this->isInternalHost($host) || $this->isPrivateHost($this->normalizedHost($this->parse($resourceUrl)['host']))) {
            return;
        }

        throw new OAuthException("OAuth endpoint [{$url}] cannot use a private, internal, or unresolvable host.");
    }

    protected function isLocalName(string $host): bool
    {
        return Str::endsWith('.'.rtrim($host, '.'), ['.localhost', '.local', '.internal', '.test']);
    }

    protected function usesProxy(): bool
    {
        foreach (['HTTPS_PROXY', 'https_proxy', 'ALL_PROXY', 'all_proxy'] as $variable) {
            if (getenv($variable)) {
                return true;
            }
        }

        return false;
    }

    protected function isInternalHost(string $host): bool
    {
        return $this->addressGuard()->isInternalHost($host);
    }

    protected function isPrivateHost(string $host): bool
    {
        return $this->addressGuard()->isPrivateHost($host);
    }

    protected function isIpAddress(string $host): bool
    {
        return $this->addressGuard()->isIpAddress($host);
    }

    protected function isPublicAddress(string $address): bool
    {
        return $this->addressGuard()->isPublicAddress($address);
    }

    /**
     * @return array<int, string>
     */
    protected function resolve(string $host): array
    {
        return $this->addressGuard()->resolve($host);
    }

    protected function isLocalhost(string $host): bool
    {
        return $this->addressGuard()->isLocalhost($host);
    }

    protected function normalizedHost(string $host): string
    {
        return $this->addressGuard()->normalizedHost($host);
    }

    protected function addressGuard(): AddressGuard
    {
        return $this->addressGuard ??= new AddressGuard;
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
