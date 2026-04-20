<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Exception;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Exceptions\ClientException;
use League\OAuth2\Client\Provider\GenericProvider;

class ClientCredentialsProvider implements AuthProvider
{
    public function __construct(protected string $clientId, protected string $clientSecret, protected string $serverName, protected ?string $tokenEndpoint = null, protected string $scope = 'mcp:use', protected ?AuthServerDiscovery $discovery = null) {}

    public function token(): ?string
    {
        $cached = Cache::get("mcp-auth:{$this->serverName}:token");

        if (is_string($cached)) {
            return $cached;
        }

        if ($this->tokenEndpoint === null) {
            return null;
        }

        return $this->acquireToken();
    }

    public function handleUnauthorized(string $wwwAuthenticate): void
    {
        if ($this->tokenEndpoint === null) {
            $this->runDiscovery($wwwAuthenticate);
        }

        $this->acquireToken();
    }

    protected function runDiscovery(string $wwwAuthenticate): void
    {
        $discovery = $this->discovery ?? new AuthServerDiscovery;
        $metadata = $discovery->discover($wwwAuthenticate);

        $this->tokenEndpoint = $metadata['token_endpoint'];
    }

    protected function acquireToken(): string
    {
        $this->ensureOAuthClientAvailable();

        $provider = $this->createOAuthProvider();

        try {
            $accessToken = $provider->getAccessToken('client_credentials', [
                'scope' => $this->scope,
            ]);

            $token = $accessToken->getToken();
            $expires = $accessToken->getExpires();

            $ttl = $expires !== null ? $expires - time() - 30 : 3600;

            if ($ttl > 0) {
                Cache::put("mcp-auth:{$this->serverName}:token", $token, $ttl);
            }

            return $token;
        } catch (Exception $exception) {
            throw new ClientException("Failed to acquire access token: {$exception->getMessage()}", 0, $exception);
        }
    }

    protected function createOAuthProvider(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'urlAccessToken' => $this->tokenEndpoint,
            'urlAuthorize' => '',
            'urlResourceOwnerDetails' => '',
        ]);
    }

    protected function ensureOAuthClientAvailable(): void
    {
        if (! class_exists(GenericProvider::class)) {
            throw new ClientException('The league/oauth2-client package is required for OAuth authentication. Install it with: composer require league/oauth2-client');
        }
    }
}
