<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use GuzzleHttp\ClientInterface;
use Laravel\Mcp\Exceptions\OAuthException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Throwable;

class OAuthHandler
{
    protected const GRANT_CLIENT_CREDENTIALS = 'client_credentials';

    protected const GRANT_REFRESH_TOKEN = 'refresh_token';

    protected const STORAGE_KEY_PREFIX = 'mcp-auth:';

    protected const STORAGE_KEY_INLINE = self::STORAGE_KEY_PREFIX.'inline';

    protected ?GenericProvider $provider = null;

    protected ?ProtectedResourceMetadata $protectedResource = null;

    protected ?AuthServerMetadata $authServer = null;

    protected ?string $authoritativeScope = null;

    protected bool $challengeRetried = false;

    public function __construct(
        protected ?string $registeredName,
        protected string $mcpUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected ?string $configuredScope,
        protected TokenStore $tokens,
        protected AuthServerDiscovery $discovery,
        protected ?ClientInterface $httpClient = null,
    ) {}

    public function bearerToken(): string
    {
        $cached = $this->tokens->get($this->storageKey());

        if ($cached instanceof TokenSet && ! $cached->isExpired()) {
            return $cached->accessToken;
        }

        return $this->tokens->lock($this->storageKey(), function (): string {
            $fresh = $this->tokens->get($this->storageKey());

            if ($fresh instanceof TokenSet && ! $fresh->isExpired()) {
                return $fresh->accessToken;
            }

            $token = $fresh instanceof TokenSet && $fresh->refreshToken !== null
                ? $this->refreshGrant($fresh->refreshToken)
                : $this->clientCredentialsGrant();

            $this->tokens->put($this->storageKey(), $token);

            return $token->accessToken;
        });
    }

    public function bearerTokenAfterChallenge(WwwAuthenticateChallenge $challenge): string
    {
        if ($this->challengeRetried) {
            throw new OAuthException("MCP client [{$this->serverLabel()}] already retried after a 401/403 challenge in this request.");
        }

        $this->challengeRetried = true;

        if ($challenge->scope !== null) {
            $this->authoritativeScope = $challenge->scope;
        }

        return $this->tokens->lock($this->storageKey(), function (): string {
            $token = $this->clientCredentialsGrant();

            $this->tokens->put($this->storageKey(), $token);

            return $token->accessToken;
        });
    }

    public function bearerTokenIfCached(): ?string
    {
        $cached = $this->tokens->get($this->storageKey());

        if (! $cached instanceof TokenSet || $cached->isExpired()) {
            return null;
        }

        return $cached->accessToken;
    }

    public function forget(): void
    {
        $this->tokens->forget($this->storageKey());
    }

    public function cachedTokens(): ?TokenSet
    {
        return $this->tokens->get($this->storageKey());
    }

    protected function clientCredentialsGrant(): TokenSet
    {
        $scope = $this->resolveScope();

        $options = ['resource' => $this->mcpUrl];

        if ($scope !== null) {
            $options['scope'] = $scope;
        }

        return $this->runGrant(self::GRANT_CLIENT_CREDENTIALS, $options);
    }

    protected function refreshGrant(string $refreshToken): TokenSet
    {
        try {
            return $this->runGrant(self::GRANT_REFRESH_TOKEN, [
                'refresh_token' => $refreshToken,
                'resource' => $this->mcpUrl,
            ]);
        } catch (OAuthException) {
            return $this->clientCredentialsGrant();
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function runGrant(string $grant, array $options): TokenSet
    {
        $provider = $this->provider();

        try {
            $accessToken = $provider->getAccessToken($grant, $options);
        } catch (IdentityProviderException $identityProviderException) {
            throw new OAuthException("MCP client [{$this->serverLabel()}] failed the {$grant} grant: {$identityProviderException->getMessage()}.", $identityProviderException->getCode(), previous: $identityProviderException);
        } catch (Throwable $throwable) {
            throw new OAuthException("MCP client [{$this->serverLabel()}] failed the {$grant} grant: {$throwable->getMessage()}.", $throwable->getCode(), previous: $throwable);
        }

        return $this->toTokenSet($accessToken);
    }

    protected function provider(): GenericProvider
    {
        if ($this->provider instanceof GenericProvider) {
            return $this->provider;
        }

        $authServer = $this->ensureDiscovered();

        $options = [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'urlAuthorize' => $authServer->authorizationEndpoint ?? '',
            'urlAccessToken' => $authServer->tokenEndpoint,
            'urlResourceOwnerDetails' => '',
        ];

        $collaborators = [];

        if ($this->httpClient instanceof ClientInterface) {
            $collaborators['httpClient'] = $this->httpClient;
        }

        return $this->provider = new GenericProvider($options, $collaborators);
    }

    protected function ensureDiscovered(): AuthServerMetadata
    {
        if ($this->authServer instanceof AuthServerMetadata) {
            return $this->authServer;
        }

        $this->protectedResource = $this->discovery->discoverProtectedResource($this->mcpUrl);

        return $this->authServer = $this->discovery->discoverAuthServer(
            $this->protectedResource->primaryAuthorizationServer(),
        );
    }

    protected function resolveScope(): ?string
    {
        if ($this->authoritativeScope !== null) {
            return $this->authoritativeScope;
        }

        if ($this->configuredScope !== null && $this->configuredScope !== '') {
            return $this->configuredScope;
        }

        $this->ensureDiscovered();

        $scopes = $this->protectedResource instanceof ProtectedResourceMetadata
            ? $this->protectedResource->scopesSupported
            : [];

        return $scopes === [] ? null : implode(' ', $scopes);
    }

    protected function toTokenSet(AccessTokenInterface $accessToken): TokenSet
    {
        $values = $accessToken->getValues();

        $scope = isset($values['scope']) && $values['scope'] !== ''
            ? (string) $values['scope']
            : null;

        return new TokenSet(
            accessToken: (string) $accessToken->getToken(),
            refreshToken: $accessToken->getRefreshToken() !== null && $accessToken->getRefreshToken() !== ''
                ? (string) $accessToken->getRefreshToken()
                : null,
            expiresAt: (int) ($accessToken->getExpires() ?? 0),
            scope: $scope,
        );
    }

    private function storageKey(): string
    {
        return $this->registeredName !== null
            ? self::STORAGE_KEY_PREFIX.$this->registeredName
            : self::STORAGE_KEY_INLINE;
    }

    private function serverLabel(): string
    {
        return $this->registeredName ?? $this->mcpUrl;
    }
}
