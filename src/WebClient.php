<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use GuzzleHttp\ClientInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route as Router;
use InvalidArgumentException;
use Laravel\Mcp\Client\Auth\AuthorizationRedirect;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\EncryptedCacheStore;
use Laravel\Mcp\Client\Auth\OAuthAuthorizationStrategy;
use Laravel\Mcp\Client\Auth\OAuthHandler;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Exceptions\UserIdentityRequiredException;
use Laravel\Mcp\Schema\Implementation;
use Throwable;

class WebClient extends Client
{
    protected ?OAuthHandler $oauthHandler = null;

    /** @var ?array{clientId: ?string, clientSecret: ?string, scope: ?string} */
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
            throw new InvalidArgumentException('Cannot call withToken() after withOauth() — choose one auth strategy per client.');
        }

        $this->httpTransport->withToken($token);
        $this->staticTokenSet = true;

        return $this;
    }

    public function withOauth(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $scope = null,
    ): static {
        if ($this->staticTokenSet) {
            throw new InvalidArgumentException('Cannot call withOauth() after withToken() — choose one auth strategy per client.');
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
        ];

        $this->wireAuthorizationStrategy();

        return $this;
    }

    public function forUser(string|int|Authenticatable|Closure|null $user): static
    {
        if ($this->oauthConfig === null || $this->oauthConfig['clientSecret'] !== null) {
            throw new InvalidArgumentException('forUser() is only valid for OAuth clients that require user consent (withOauth() with no client secret).');
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
        $clone->oauthHttpClient = $this->oauthHttpClient;
        $clone->wireAuthorizationStrategy();

        return $clone;
    }

    public function tokens(): ?TokenSet
    {
        return $this->oauthConfig === null ? null : $this->oauthHandler()->cachedTokens();
    }

    public function forgetTokens(): void
    {
        if ($this->oauthConfig !== null) {
            $this->oauthHandler()->forget();
        }
    }

    public function needsAuthorization(): bool
    {
        return $this->oauthConfig !== null && $this->oauthHandler()->needsAuthorization();
    }

    public function requiresUserConsent(): bool
    {
        return $this->oauthConfig !== null && $this->oauthHandler()->requiresUserConsent();
    }

    public function startAuthorization(?string $intendedUrl = null): AuthorizationRedirect
    {
        return $this->oauthHandler()->startAuthorization($intendedUrl);
    }

    public function completeAuthorization(string $code, string $state): TokenSet
    {
        return $this->oauthHandler()->completeAuthorization($code, $state);
    }

    public function lastIntendedUrl(): ?string
    {
        return $this->oauthConfig === null ? null : $this->oauthHandler()->lastIntendedUrl();
    }

    public function authorizationConnectUrl(?string $intended = null): string
    {
        $this->assertRegisteredForRoutes();

        $params = ['server' => $this->registeredName];

        if ($intended !== null && $intended !== '') {
            $params['intended'] = $intended;
        }

        if (Router::has('mcp.oauth.connect')) {
            return route('mcp.oauth.connect', $params);
        }

        $queryParams = $intended === null ? [] : ['intended' => $intended];

        return (string) url('/mcp/'.$this->registeredName.'/connect', $queryParams);
    }

    public function redirectToAuthorization(?string $intended = null): RedirectResponse
    {
        if ($intended === null && Container::getInstance()->bound('request')) {
            $intended = request()->fullUrl();
        }

        return redirect()->to($this->authorizationConnectUrl($intended));
    }

    private function assertRegisteredForRoutes(): void
    {
        if ($this->registeredName === null) {
            throw new InvalidArgumentException('authorizationConnectUrl() / redirectToAuthorization() require a registered client (Mcp::registerClient).');
        }
    }

    /**
     * @internal Used by tests to inject a stubbed Guzzle client into the league provider.
     */
    public function withOauthHttpClient(?ClientInterface $client): static
    {
        $this->oauthHttpClient = $client;
        $this->oauthHandler = null;
        $this->wireAuthorizationStrategy();

        return $this;
    }

    private function wireAuthorizationStrategy(): void
    {
        $this->httpTransport->withAuthorizationStrategy(new OAuthAuthorizationStrategy(
            handlerResolver: fn (): OAuthHandler => $this->oauthHandler(),
        ));
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

        return $this->oauthHandler = new OAuthHandler(
            serverName: $this->registeredName,
            mcpUrl: $this->httpTransport->url(),
            clientId: $config['clientId'],
            clientSecret: $config['clientSecret'],
            configuredScope: $config['scope'],
            store: $this->resolveStore(),
            discovery: new AuthServerDiscovery,
            httpClient: $this->oauthHttpClient,
            redirectUriResolver: $this->resolveRedirectUriResolver(),
            userKey: $this->resolveUserKey(),
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

    private function resolveRedirectUriResolver(): ?Closure
    {
        if ($this->registeredName === null) {
            return null;
        }

        return fn (): string => Router::has('mcp.oauth.callback')
            ? route('mcp.oauth.callback', ['server' => $this->registeredName])
            : (string) url('/mcp/'.$this->registeredName.'/callback');
    }

    protected function resolveStore(): EncryptedCacheStore
    {
        $crypt = $this->encrypterOrNull();

        if (! $crypt instanceof StringEncrypter) {
            return new EncryptedCacheStore(
                cache: new CacheRepository(new ArrayStore(serializesValues: true)),
                crypt: new Encrypter(random_bytes(32), 'AES-256-CBC'),
            );
        }

        $container = Container::getInstance();

        $cache = $container->bound(Repository::class)
            ? $container->make(Repository::class)
            : new CacheRepository(new ArrayStore(serializesValues: true));

        return new EncryptedCacheStore(cache: $cache, crypt: $crypt);
    }

    private function encrypterOrNull(): ?StringEncrypter
    {
        $container = Container::getInstance();

        if (! $container->bound(Repository::class)) {
            return null;
        }

        try {
            if ($container->bound(StringEncrypter::class)) {
                $crypt = $container->make(StringEncrypter::class);
            } elseif ($container->bound('encrypter')) {
                $crypt = $container->make('encrypter');
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $crypt instanceof StringEncrypter ? $crypt : null;
    }
}
