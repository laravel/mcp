<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use GuzzleHttp\ClientInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route as Router;
use InvalidArgumentException;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\CacheClientRegistrationStore;
use Laravel\Mcp\Client\Auth\CacheTokenStore;
use Laravel\Mcp\Client\Auth\ClientRegistrationStore;
use Laravel\Mcp\Client\Auth\InMemoryClientRegistrationStore;
use Laravel\Mcp\Client\Auth\InMemoryTokenStore;
use Laravel\Mcp\Client\Auth\OAuthClientStateStore;
use Laravel\Mcp\Client\Auth\OAuthHandler;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Client\Auth\TokenStore;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Exceptions\UserIdentityRequiredException;
use Laravel\Mcp\Schema\Implementation;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class WebClient extends Client
{
    protected ?OAuthHandler $oauthHandler = null;

    /** @var ?array{clientId: ?string, clientSecret: ?string, scope: ?string, onAuthRequired: ?Closure} */
    protected ?array $oauthConfig = null;

    protected bool $staticTokenSet = false;

    protected mixed $userKey = null;

    protected ?ClientInterface $oauthHttpClient = null;

    public function __construct(
        protected HttpTransport $httpTransport,
        ?Implementation $clientInfo = null,
    ) {
        parent::__construct($httpTransport, $clientInfo);
    }

    public function withToken(string $token): static
    {
        if ($this->oauthConfig !== null) {
            throw new InvalidArgumentException('Cannot call withToken() after oauth() — choose one auth strategy per client.');
        }

        $this->httpTransport->withToken($token);
        $this->staticTokenSet = true;

        return $this;
    }

    public function oauth(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $scope = null,
        ?Closure $onAuthRequired = null,
    ): static {
        if ($this->staticTokenSet) {
            throw new InvalidArgumentException('Cannot call oauth() after withToken() — choose one auth strategy per client.');
        }

        if ($this->oauthConfig !== null) {
            throw new InvalidArgumentException('OAuth has already been configured for this client.');
        }

        if ($clientId === null && $clientSecret !== null) {
            throw new InvalidArgumentException('Dynamic client registration cannot be combined with a client secret.');
        }

        $this->oauthConfig = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'scope' => $scope,
            'onAuthRequired' => $onAuthRequired,
        ];

        $this->wireOauthTransport();

        return $this;
    }

    public function forUser(string|int|Authenticatable|Closure|null $user): static
    {
        if ($this->oauthConfig === null || $this->oauthConfig['clientSecret'] !== null) {
            throw new InvalidArgumentException('forUser() is only valid for authorization_code OAuth clients (oauth() with no client secret).');
        }

        if ($user === null) {
            throw new InvalidArgumentException('forUser() requires an Authenticatable, id, or Closure that resolves one — null is not allowed.');
        }

        $clone = new static(new HttpTransport($this->httpTransport->url()));
        $clone->oauthConfig = $this->oauthConfig;
        $clone->registeredName = $this->registeredName;
        $clone->listCacheTtl = $this->listCacheTtl;
        $clone->cacheScope = $this->cacheScope;
        $clone->userKey = $user;
        $clone->wireOauthTransport();

        return $clone;
    }

    public function tokens(): ?TokenSet
    {
        if ($this->oauthConfig === null) {
            return null;
        }

        return $this->oauthHandler()->cachedTokens();
    }

    public function forgetTokens(): void
    {
        if ($this->oauthConfig === null) {
            return;
        }

        $this->oauthHandler()->forget();
    }

    public function needsAuthorization(): bool
    {
        if ($this->oauthConfig === null) {
            return false;
        }

        return $this->oauthHandler()->needsAuthorization();
    }

    public function oauthHandlerForRoutes(): OAuthHandler
    {
        return $this->oauthHandler();
    }

    public function withOauthHttpClient(?ClientInterface $client): static
    {
        $this->oauthHttpClient = $client;
        $this->oauthHandler = null;

        return $this;
    }

    private function wireOauthTransport(): void
    {
        $this->httpTransport->withTokenProvider(fn (): string => $this->resolveBearerToken());
        $this->httpTransport->withChallengeHandler(fn (WwwAuthenticateChallenge $challenge): string => $this->oauthHandler()->bearerTokenAfterChallenge($challenge));
        $this->httpTransport->withCachedBearerResolver(fn (): ?string => $this->oauthHandler()->bearerTokenIfCached());
    }

    private function resolveBearerToken(): string
    {
        try {
            return $this->oauthHandler()->bearerToken();
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            $callback = $this->oauthConfig['onAuthRequired'] ?? null;

            if ($callback instanceof Closure) {
                $response = $callback($authorizationRequiredException);

                if ($response instanceof Responsable) {
                    $response = $response->toResponse(request());
                }

                if ($response instanceof SymfonyResponse) {
                    throw new HttpResponseException($response);
                }

                throw $authorizationRequiredException;
            }

            if ($this->canAutoRedirectToConnect()) {
                throw new HttpResponseException(redirect()->route('mcp.oauth.connect', [
                    'server' => $this->registeredName,
                    'intended' => request()->fullUrl(),
                ]));
            }

            throw $authorizationRequiredException;
        }
    }

    private function canAutoRedirectToConnect(): bool
    {
        if ($this->registeredName === null) {
            return false;
        }

        if (! Container::getInstance()->bound('request')) {
            return false;
        }

        return Router::has('mcp.oauth.connect');
    }

    private function oauthHandler(): OAuthHandler
    {
        if ($this->oauthHandler instanceof OAuthHandler) {
            return $this->oauthHandler;
        }

        $config = $this->oauthConfig;

        if ($config === null) {
            throw new InvalidArgumentException('OAuth has not been configured for this client.');
        }

        $userKey = $this->resolveUserKey();

        return $this->oauthHandler = new OAuthHandler(
            registeredName: $this->registeredName,
            mcpUrl: $this->httpTransport->url(),
            clientId: $config['clientId'],
            clientSecret: $config['clientSecret'],
            configuredScope: $config['scope'],
            tokens: $this->resolveTokenStore(),
            discovery: new AuthServerDiscovery,
            httpClient: $this->oauthHttpClient,
            stateStore: $this->resolveStateStore(),
            redirectUriResolver: $this->registeredName !== null
                ? fn (): string => Router::has('mcp.oauth.callback')
                    ? route('mcp.oauth.callback', ['server' => $this->registeredName])
                    : (string) url('/mcp/'.$this->registeredName.'/callback')
                : null,
            userKey: $userKey,
            registrationStore: $this->resolveRegistrationStore(),
        );
    }

    private function resolveUserKey(): ?string
    {
        if ($this->userKey === null) {
            return null;
        }

        $value = $this->userKey instanceof Closure ? ($this->userKey)() : $this->userKey;

        if ($value instanceof Authenticatable) {
            $value = $value->getAuthIdentifier();
        }

        if ($value === null || $value === '') {
            throw new UserIdentityRequiredException($this->registeredName ?? $this->httpTransport->url());
        }

        return (string) $value;
    }

    protected function resolveTokenStore(): TokenStore
    {
        if ($this->registeredName === null) {
            return new InMemoryTokenStore;
        }

        $container = Container::getInstance();

        if (! $container->bound(Repository::class)) {
            return new InMemoryTokenStore;
        }

        try {
            $crypt = $container->bound(StringEncrypter::class)
                ? $container->make(StringEncrypter::class)
                : ($container->bound('encrypter') ? $container->make('encrypter') : null);
        } catch (Throwable) {
            return new InMemoryTokenStore;
        }

        if (! $crypt instanceof StringEncrypter) {
            return new InMemoryTokenStore;
        }

        return new CacheTokenStore(
            cache: $container->make(Repository::class),
            crypt: $crypt,
        );
    }

    protected function resolveStateStore(): ?OAuthClientStateStore
    {
        $container = Container::getInstance();

        if (! $container->bound(Repository::class)) {
            return null;
        }

        return new OAuthClientStateStore($container->make(Repository::class));
    }

    protected function resolveRegistrationStore(): ClientRegistrationStore
    {
        if ($this->registeredName === null) {
            return new InMemoryClientRegistrationStore;
        }

        $container = Container::getInstance();

        if (! $container->bound(Repository::class)) {
            return new InMemoryClientRegistrationStore;
        }

        try {
            $crypt = $container->bound(StringEncrypter::class)
                ? $container->make(StringEncrypter::class)
                : ($container->bound('encrypter') ? $container->make('encrypter') : null);
        } catch (Throwable) {
            return new InMemoryClientRegistrationStore;
        }

        if (! $crypt instanceof StringEncrypter) {
            return new InMemoryClientRegistrationStore;
        }

        return new CacheClientRegistrationStore(
            cache: $container->make(Repository::class),
            crypt: $crypt,
        );
    }
}
