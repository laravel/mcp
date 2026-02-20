<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Exception;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Exceptions\ClientException;
use League\OAuth2\Client\Provider\GenericProvider;

class AuthorizationCodeProvider implements AuthProvider
{
    public function __construct(protected string $clientId, protected string $redirectUri, protected string $serverName, protected ?string $authorizationEndpoint = null, protected ?string $tokenEndpoint = null, protected string $scope = 'mcp:use', protected ?AuthServerDiscovery $discovery = null) {}

    public function token(): ?string
    {
        $cached = Cache::get("mcp-auth:{$this->serverName}:token");

        if (is_string($cached)) {
            return $cached;
        }

        $refreshToken = Cache::get("mcp-auth:{$this->serverName}:refresh_token");

        if (is_string($refreshToken) && $this->tokenEndpoint !== null) {
            return $this->refreshAccessToken($refreshToken);
        }

        return null;
    }

    public function handleUnauthorized(string $wwwAuthenticate): void
    {
        if ($this->tokenEndpoint === null || $this->authorizationEndpoint === null) {
            $this->runDiscovery($wwwAuthenticate);
        }

        [$url, $state] = $this->authorizationUrl();

        throw new AuthorizationRequiredException($url, $state, $this);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function authorizationUrl(): array
    {
        $this->ensureOAuthClientAvailable();

        $state = bin2hex(random_bytes(16));
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        Cache::put("mcp-auth:{$this->serverName}:state", $state, 600);
        Cache::put("mcp-auth:{$this->serverName}:code_verifier", $codeVerifier, 600);

        $provider = $this->createOAuthProvider();

        $url = $provider->getAuthorizationUrl([
            'state' => $state,
            'scope' => $this->scope,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return [$url, $state];
    }

    public function exchangeCode(string $code, string $state): string
    {
        $this->ensureOAuthClientAvailable();

        $storedState = Cache::pull("mcp-auth:{$this->serverName}:state");

        if ($storedState !== $state) {
            throw new ClientException('Invalid OAuth state parameter.');
        }

        /** @var string $codeVerifier */
        $codeVerifier = Cache::pull("mcp-auth:{$this->serverName}:code_verifier");

        if ($codeVerifier === null) {
            throw new ClientException('PKCE code verifier not found. The authorization request may have expired.');
        }

        $provider = $this->createOAuthProvider();

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $code,
                'code_verifier' => $codeVerifier,
            ]);

            $token = $accessToken->getToken();
            $expires = $accessToken->getExpires();
            $refreshToken = $accessToken->getRefreshToken();

            $ttl = $expires !== null ? $expires - time() - 30 : 3600;

            if ($ttl > 0) {
                Cache::put("mcp-auth:{$this->serverName}:token", $token, $ttl);
            }

            if ($refreshToken !== null) {
                Cache::put("mcp-auth:{$this->serverName}:refresh_token", $refreshToken, 86400);
            }

            return $token;
        } catch (Exception $exception) {
            throw new ClientException("Failed to exchange authorization code: {$exception->getMessage()}", 0, $exception);
        }
    }

    protected function runDiscovery(string $wwwAuthenticate): void
    {
        $discovery = $this->discovery ?? new AuthServerDiscovery;
        $metadata = $discovery->discover($wwwAuthenticate);

        $this->tokenEndpoint = $metadata['token_endpoint'];

        if (isset($metadata['authorization_endpoint'])) {
            $this->authorizationEndpoint = $metadata['authorization_endpoint'];
        }
    }

    protected function refreshAccessToken(string $refreshToken): ?string
    {
        $this->ensureOAuthClientAvailable();

        $provider = $this->createOAuthProvider();

        try {
            $accessToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

            $token = $accessToken->getToken();
            $expires = $accessToken->getExpires();
            $newRefreshToken = $accessToken->getRefreshToken();

            $ttl = $expires !== null ? $expires - time() - 30 : 3600;

            if ($ttl > 0) {
                Cache::put("mcp-auth:{$this->serverName}:token", $token, $ttl);
            }

            if ($newRefreshToken !== null) {
                Cache::put("mcp-auth:{$this->serverName}:refresh_token", $newRefreshToken, 86400);
            }

            return $token;
        } catch (Exception) {
            Cache::forget("mcp-auth:{$this->serverName}:refresh_token");

            return null;
        }
    }

    protected function createOAuthProvider(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => '',
            'redirectUri' => $this->redirectUri,
            'urlAuthorize' => $this->authorizationEndpoint ?? '',
            'urlAccessToken' => $this->tokenEndpoint ?? '',
            'urlResourceOwnerDetails' => '',
        ]);
    }

    protected function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    protected function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    protected function ensureOAuthClientAvailable(): void
    {
        if (! class_exists(GenericProvider::class)) {
            throw new ClientException('The league/oauth2-client package is required for OAuth authentication. Install it with: composer require league/oauth2-client');
        }
    }
}
