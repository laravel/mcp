<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use InvalidArgumentException;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\CacheTokenStore;
use Laravel\Mcp\Client\Auth\InMemoryTokenStore;
use Laravel\Mcp\Client\Auth\OAuthHandler;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Client\Auth\TokenStore;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Schema\Implementation;

class WebClient extends Client
{
    protected ?OAuthHandler $oauthHandler = null;

    /** @var ?array{clientId: string, clientSecret: string, scope: ?string} */
    protected ?array $oauthConfig = null;

    protected bool $staticTokenSet = false;

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

    public function oauth(string $clientId, string $clientSecret, ?string $scope = null): static
    {
        if ($this->staticTokenSet) {
            throw new InvalidArgumentException('Cannot call oauth() after withToken() — choose one auth strategy per client.');
        }

        if ($this->oauthConfig !== null) {
            throw new InvalidArgumentException('OAuth has already been configured for this client.');
        }

        $this->oauthConfig = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'scope' => $scope,
        ];

        $this->httpTransport->withTokenProvider(fn (): string => $this->oauthHandler()->bearerToken());
        $this->httpTransport->withChallengeHandler(fn (WwwAuthenticateChallenge $challenge): string => $this->oauthHandler()->bearerTokenAfterChallenge($challenge));
        $this->httpTransport->withCachedBearerResolver(fn (): ?string => $this->oauthHandler()->bearerTokenIfCached());

        return $this;
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
            registeredName: $this->registeredName,
            mcpUrl: $this->httpTransport->url(),
            clientId: $config['clientId'],
            clientSecret: $config['clientSecret'],
            configuredScope: $config['scope'],
            tokens: $this->resolveTokenStore(),
            discovery: new AuthServerDiscovery,
        );
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

        $crypt = $container->bound(StringEncrypter::class)
            ? $container->make(StringEncrypter::class)
            : ($container->bound('encrypter') ? $container->make('encrypter') : null);

        if (! $crypt instanceof StringEncrypter) {
            return new InMemoryTokenStore;
        }

        return new CacheTokenStore(
            cache: $container->make(Repository::class),
            crypt: $crypt,
        );
    }
}
