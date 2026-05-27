<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;
use GuzzleHttp\ClientInterface;
use Laravel\Mcp\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Exceptions\OAuthException;
use Laravel\Mcp\Exceptions\PkceUnsupportedException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Throwable;

class OAuthHandler
{
    protected const GRANT_CLIENT_CREDENTIALS = 'client_credentials';

    protected const GRANT_AUTHORIZATION_CODE = 'authorization_code';

    protected const GRANT_REFRESH_TOKEN = 'refresh_token';

    protected const STORAGE_KEY_PREFIX = 'mcp-auth:';

    protected const STORAGE_KEY_INLINE = self::STORAGE_KEY_PREFIX.'inline';

    protected ?GenericProvider $provider = null;

    protected ?ProtectedResourceMetadata $protectedResource = null;

    protected ?AuthServerMetadata $authServer = null;

    protected ?string $authoritativeScope = null;

    protected ?string $canonicalResource = null;

    protected bool $challengeRetried = false;

    protected bool $dynamic = false;

    protected ?string $lastIntendedUrl = null;

    /**
     * @param  ?Closure(): string  $redirectUriResolver
     */
    public function __construct(
        protected ?string $registeredName,
        protected string $mcpUrl,
        protected ?string $clientId,
        protected ?string $clientSecret,
        protected ?string $configuredScope,
        protected TokenStore $tokens,
        protected AuthServerDiscovery $discovery,
        protected ?ClientInterface $httpClient = null,
        protected ?OAuthClientStateStore $stateStore = null,
        protected ?Closure $redirectUriResolver = null,
        protected ?string $userKey = null,
        protected ?ClientRegistrationStore $registrationStore = null,
        protected ?DynamicClientRegistration $dynamicRegistration = null,
    ) {
        $this->dynamic = $clientId === null;
    }

    public function isAuthorizationCode(): bool
    {
        return $this->dynamic || $this->clientSecret === null;
    }

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

            $token = $this->acquireToken($fresh);

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
            if ($this->isAuthorizationCode()) {
                $cached = $this->tokens->get($this->storageKey());

                if ($cached instanceof TokenSet && $cached->refreshToken !== null) {
                    $token = $this->refreshGrant($cached->refreshToken);
                    $this->tokens->put($this->storageKey(), $token);

                    return $token->accessToken;
                }

                throw $this->authorizationRequired();
            }

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

    public function needsAuthorization(): bool
    {
        if (! $this->isAuthorizationCode()) {
            return false;
        }

        return ! $this->tokens->get($this->storageKey()) instanceof TokenSet;
    }

    public function forget(): void
    {
        $this->tokens->forget($this->storageKey());
    }

    public function cachedTokens(): ?TokenSet
    {
        return $this->tokens->get($this->storageKey());
    }

    public function startAuthorization(?string $intendedUrl = null): AuthorizationRedirect
    {
        if (! $this->isAuthorizationCode()) {
            throw new OAuthException('startAuthorization() is only valid for authorization_code OAuth clients.');
        }

        $authServer = $this->ensureDiscovered();

        if (! $authServer->supportsPkceS256()) {
            throw new PkceUnsupportedException($authServer->issuer);
        }

        $this->ensureRegistered();

        $pkce = Pkce::generate();
        $state = bin2hex(random_bytes(16));
        $scope = $this->resolveScope();

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->resolveRedirectUri(),
            'state' => $state,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => $pkce->method,
            'resource' => $this->canonicalResource(),
        ];

        if ($scope !== null) {
            $params['scope'] = $scope;
        }

        $authorizationEndpoint = $authServer->authorizationEndpoint;

        if ($authorizationEndpoint === null || $authorizationEndpoint === '') {
            throw new OAuthException("Authorization server [{$authServer->issuer}] is missing the authorization_endpoint.");
        }

        $url = $authorizationEndpoint.(str_contains($authorizationEndpoint, '?') ? '&' : '?').http_build_query($params);

        $this->stateStore?->put($state, [
            'server' => $this->serverLabel(),
            'user_key' => $this->userKey,
            'pkce_verifier' => $pkce->verifier,
            'intended_url' => $intendedUrl,
            'scope' => $scope,
        ]);

        return new AuthorizationRedirect($url, $state);
    }

    public function completeAuthorization(string $code, string $state): TokenSet
    {
        if (! $this->isAuthorizationCode()) {
            throw new OAuthException('completeAuthorization() is only valid for authorization_code OAuth clients.');
        }

        if (! $this->stateStore instanceof OAuthClientStateStore) {
            throw new OAuthException('Cannot complete authorization without a configured state store.');
        }

        $payload = $this->stateStore->pull($state);

        if ($payload === null) {
            throw new OAuthException("OAuth state [{$state}] is invalid or expired.");
        }

        $this->lastIntendedUrl = $payload['intended_url'] ?? null;

        $token = $this->runGrant(self::GRANT_AUTHORIZATION_CODE, [
            'code' => $code,
            'code_verifier' => $payload['pkce_verifier'],
            'redirect_uri' => $this->resolveRedirectUri(),
            'resource' => $this->canonicalResource(),
        ]);

        $this->tokens->put($this->storageKey(), $token);

        return $token;
    }

    public function lastIntendedUrl(): ?string
    {
        return $this->lastIntendedUrl;
    }

    public function authorizationRequired(): AuthorizationRequiredException
    {
        if (! $this->isAuthorizationCode()) {
            return new AuthorizationRequiredException($this->serverLabel());
        }

        try {
            $redirect = $this->startAuthorization();
        } catch (Throwable) {
            return new AuthorizationRequiredException($this->serverLabel());
        }

        return new AuthorizationRequiredException(
            serverName: $this->serverLabel(),
            authorizationUrl: $redirect->url,
            state: $redirect->state,
            resourceMetadataUrl: $this->protectedResourceMetadataUrl(),
        );
    }

    protected function acquireToken(?TokenSet $current): TokenSet
    {
        if ($this->isAuthorizationCode()) {
            if ($current instanceof TokenSet && $current->refreshToken !== null) {
                try {
                    return $this->refreshGrant($current->refreshToken);
                } catch (OAuthException) {
                    //
                }
            }

            throw $this->authorizationRequired();
        }

        if ($current instanceof TokenSet && $current->refreshToken !== null) {
            return $this->refreshGrant($current->refreshToken);
        }

        return $this->clientCredentialsGrant();
    }

    protected function clientCredentialsGrant(): TokenSet
    {
        $scope = $this->resolveScope();

        $options = ['resource' => $this->canonicalResource()];

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
                'resource' => $this->canonicalResource(),
            ]);
        } catch (OAuthException $oAuthException) {
            if ($this->isAuthorizationCode()) {
                throw $oAuthException;
            }

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
        $this->ensureRegistered();

        $options = [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret ?? '',
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

    protected function ensureRegistered(): void
    {
        if (! $this->dynamic) {
            return;
        }

        if ($this->clientId !== null && $this->clientId !== '') {
            return;
        }

        $registry = $this->registrationStore;
        $registryKey = $this->registrationKey();

        if ($registry instanceof ClientRegistrationStore) {
            $cached = $registry->get($registryKey);

            if ($cached instanceof ClientRegistration && ! $cached->isSecretExpired()) {
                $this->clientId = $cached->clientId;
                $this->clientSecret = $cached->clientSecret;

                return;
            }
        }

        $authServer = $this->ensureDiscovered();

        if (! $authServer->supportsDynamicRegistration()) {
            throw new OAuthException("Authorization server [{$authServer->issuer}] does not advertise a registration_endpoint; cannot dynamically register MCP client [{$this->serverLabel()}].");
        }

        $dcr = $this->dynamicRegistration ?? new DynamicClientRegistration;

        $registration = $dcr->register((string) $authServer->registrationEndpoint, [
            'redirect_uris' => [$this->resolveRedirectUri()],
            'scope' => $this->resolveScope(),
            'public_client' => true,
        ]);

        $this->clientId = $registration->clientId;
        $this->clientSecret = $registration->clientSecret;

        $registry?->put($registryKey, $registration);
    }

    protected function registrationKey(): string
    {
        return 'mcp-client:'.($this->registeredName ?? 'inline');
    }

    protected function canonicalResource(): string
    {
        if ($this->canonicalResource !== null) {
            return $this->canonicalResource;
        }

        $parts = parse_url($this->mcpUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $this->canonicalResource = $this->mcpUrl;
        }

        $url = strtolower($parts['scheme']).'://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        if (isset($parts['path']) && $parts['path'] !== '/' && $parts['path'] !== '') {
            $url .= rtrim($parts['path'], '/');
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?'.$parts['query'];
        }

        return $this->canonicalResource = $url;
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

    protected function resolveRedirectUri(): string
    {
        if (! $this->redirectUriResolver instanceof Closure) {
            throw new OAuthException('No redirect URI resolver is configured for this authorization_code client.');
        }

        return ($this->redirectUriResolver)();
    }

    protected function protectedResourceMetadataUrl(): ?string
    {
        if (! $this->protectedResource instanceof ProtectedResourceMetadata) {
            return null;
        }

        return $this->protectedResource->resource;
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
        $name = $this->registeredName ?? 'inline';
        $userSegment = $this->userKey !== null ? ":user:{$this->userKey}" : '';

        return self::STORAGE_KEY_PREFIX.$name.$userSegment;
    }

    private function serverLabel(): string
    {
        return $this->registeredName ?? $this->mcpUrl;
    }
}
