<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;
use GuzzleHttp\ClientInterface;
use Laravel\Mcp\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Exceptions\OAuthException;
use Laravel\Mcp\Exceptions\PkceUnsupportedException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Throwable;

class OAuthHandler
{
    protected const GRANT_CLIENT_CREDENTIALS = 'client_credentials';

    protected const GRANT_AUTHORIZATION_CODE = 'authorization_code';

    protected const GRANT_REFRESH_TOKEN = 'refresh_token';

    protected const PKCE_METHOD = 'S256';

    protected ?GenericProvider $provider = null;

    protected ?ProtectedResourceMetadata $protectedResource = null;

    protected ?AuthServerMetadata $authServer = null;

    protected ?string $authoritativeScope = null;

    protected ?string $canonicalResource = null;

    protected ?string $resolvedRedirectUri = null;

    protected bool $challengeRetried = false;

    protected ?string $lastIntendedUrl = null;

    /**
     * @param  ?Closure(): string  $redirectUriResolver
     */
    public function __construct(
        protected ?string $serverName,
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
    ) {}

    public function isDynamic(): bool
    {
        return $this->clientId === null;
    }

    public function requiresUserConsent(): bool
    {
        if ($this->isDynamic()) {
            return true;
        }

        return $this->clientSecret === null;
    }

    public function bearerToken(): string
    {
        $key = $this->tokenKey();
        $cached = $this->tokens->get($key);

        if ($cached instanceof TokenSet && ! $cached->isExpired()) {
            return $cached->accessToken;
        }

        return $this->tokens->lock($key, function () use ($key): string {
            $fresh = $this->tokens->get($key);

            if ($fresh instanceof TokenSet && ! $fresh->isExpired()) {
                return $fresh->accessToken;
            }

            $token = $this->acquireToken($fresh);
            $this->tokens->put($key, $token);

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

        $key = $this->tokenKey();

        return $this->tokens->lock($key, function () use ($key): string {
            if ($this->requiresUserConsent()) {
                $cached = $this->tokens->get($key);

                if ($cached instanceof TokenSet && $cached->refreshToken !== null) {
                    $token = $this->refreshGrant($cached->refreshToken);
                    $this->tokens->put($key, $token);

                    return $token->accessToken;
                }

                throw $this->authorizationRequired();
            }

            $token = $this->clientCredentialsGrant();
            $this->tokens->put($key, $token);

            return $token->accessToken;
        });
    }

    public function bearerTokenIfCached(): ?string
    {
        $cached = $this->tokens->get($this->tokenKey());

        if (! $cached instanceof TokenSet || $cached->isExpired()) {
            return null;
        }

        return $cached->accessToken;
    }

    public function needsAuthorization(): bool
    {
        if (! $this->requiresUserConsent()) {
            return false;
        }

        return ! $this->tokens->get($this->tokenKey()) instanceof TokenSet;
    }

    public function forget(): void
    {
        $this->tokens->forget($this->tokenKey());
    }

    public function cachedTokens(): ?TokenSet
    {
        return $this->tokens->get($this->tokenKey());
    }

    public function startAuthorization(?string $intendedUrl = null): AuthorizationRedirect
    {
        if (! $this->requiresUserConsent()) {
            throw new OAuthException('startAuthorization() is only valid for OAuth clients that require user consent.');
        }

        $authServer = $this->ensureDiscovered();

        if (! $authServer->supportsPkceS256()) {
            throw new PkceUnsupportedException($authServer->issuer);
        }

        if ($authServer->authorizationEndpoint === null || $authServer->authorizationEndpoint === '') {
            throw new OAuthException("Authorization server [{$authServer->issuer}] is missing the authorization_endpoint.");
        }

        $this->ensureRegistered();

        $provider = $this->provider();
        $state = bin2hex(random_bytes(16));
        $scope = $this->resolveScope();

        $options = [
            'state' => $state,
            'resource' => $this->canonicalResource(),
        ];

        if ($scope !== null) {
            $options['scope'] = $scope;
        }

        $url = $provider->getAuthorizationUrl($options);
        $verifier = $provider->getPkceCode();

        if (! is_string($verifier) || $verifier === '') {
            throw new OAuthException('league/oauth2-client did not produce a PKCE verifier.');
        }

        $this->stateStore?->put($state, new OAuthSession(
            serverName: $this->serverLabel(),
            pkceVerifier: $verifier,
            userKey: $this->userKey,
            intendedUrl: $intendedUrl,
            scope: $scope,
        ));

        return new AuthorizationRedirect($url, $state);
    }

    public function completeAuthorization(string $code, string $state): TokenSet
    {
        if (! $this->requiresUserConsent()) {
            throw new OAuthException('completeAuthorization() is only valid for OAuth clients that require user consent.');
        }

        if (! $this->stateStore instanceof OAuthClientStateStore) {
            throw new OAuthException('Cannot complete authorization without a configured state store.');
        }

        $session = $this->stateStore->pull($state);

        if (! $session instanceof OAuthSession) {
            throw new OAuthException("OAuth state [{$state}] is invalid or expired.");
        }

        $this->lastIntendedUrl = $session->intendedUrl;

        $token = $this->runGrant(self::GRANT_AUTHORIZATION_CODE, [
            'code' => $code,
            'code_verifier' => $session->pkceVerifier,
            'redirect_uri' => $this->resolveRedirectUri(),
            'resource' => $this->canonicalResource(),
        ]);

        $this->tokens->put($this->tokenKey(), $token);

        return $token;
    }

    public function lastIntendedUrl(): ?string
    {
        return $this->lastIntendedUrl;
    }

    public function authorizationRequired(): AuthorizationRequiredException
    {
        if (! $this->requiresUserConsent()) {
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
        if ($this->requiresUserConsent()) {
            if ($current instanceof TokenSet && $current->refreshToken !== null) {
                try {
                    return $this->refreshGrant($current->refreshToken);
                } catch (OAuthException) {
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
            if ($this->requiresUserConsent()) {
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
        try {
            $accessToken = $this->provider()->getAccessToken($grant, $options);
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

        if ($this->requiresUserConsent()) {
            $options['redirectUri'] = $this->resolveRedirectUri();
            $options['pkceMethod'] = self::PKCE_METHOD;
        }

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
        if (! $this->isDynamic() || ($this->clientId !== null && $this->clientId !== '')) {
            return;
        }

        $registryKey = $this->registrationKey();

        if ($this->registrationStore instanceof ClientRegistrationStore) {
            $cached = $this->registrationStore->get($registryKey);

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

        $registration = ($this->dynamicRegistration ?? new DynamicClientRegistration)->register(
            (string) $authServer->registrationEndpoint,
            [
                'redirect_uris' => [$this->resolveRedirectUri()],
                'scope' => $this->resolveScope(),
                'public_client' => true,
            ]
        );

        $this->clientId = $registration->clientId;
        $this->clientSecret = $registration->clientSecret;
        $this->registrationStore?->put($registryKey, $registration);
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
        if ($this->resolvedRedirectUri !== null) {
            return $this->resolvedRedirectUri;
        }

        if (! $this->redirectUriResolver instanceof Closure) {
            throw new OAuthException('No redirect URI resolver is configured for this authorization_code client.');
        }

        return $this->resolvedRedirectUri = ($this->redirectUriResolver)();
    }

    protected function protectedResourceMetadataUrl(): ?string
    {
        return $this->protectedResource?->resource;
    }

    protected function toTokenSet(AccessTokenInterface $accessToken): TokenSet
    {
        $values = $accessToken->getValues();
        $rawRefresh = $accessToken->getRefreshToken();

        return new TokenSet(
            accessToken: (string) $accessToken->getToken(),
            refreshToken: $rawRefresh !== null && $rawRefresh !== '' ? (string) $rawRefresh : null,
            expiresAt: (int) ($accessToken->getExpires() ?? 0),
            scope: isset($values['scope']) && $values['scope'] !== '' ? (string) $values['scope'] : null,
        );
    }

    private function tokenKey(): string
    {
        return OAuthCacheKeys::tokens($this->serverName ?? 'inline', $this->userKey);
    }

    private function registrationKey(): string
    {
        return OAuthCacheKeys::registration($this->serverName ?? 'inline');
    }

    private function serverLabel(): string
    {
        return $this->serverName ?? $this->mcpUrl;
    }
}
