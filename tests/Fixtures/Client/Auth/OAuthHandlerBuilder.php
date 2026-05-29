<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Encryption\Encrypter;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\EncryptedCacheStore;
use Laravel\Mcp\Client\Auth\OAuthHandler;
use Laravel\Mcp\Client\Auth\TokenSet;

final class OAuthHandlerBuilder
{
    public EncryptedCacheStore $store;

    /**
     * @var array<string, mixed>
     */
    private array $overrides = [];

    /**
     * @var list<PsrResponse>
     */
    private array $grants = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $history = [];

    private ?OAuthHandler $handler = null;

    public function __construct()
    {
        $this->store = new EncryptedCacheStore(
            cache: new CacheRepository(new ArrayStore(serializesValues: true)),
            crypt: new Encrypter(random_bytes(32), 'AES-256-CBC'),
        );
    }

    /**
     * Override one or more OAuthHandler constructor arguments by name.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        $this->overrides = array_merge($this->overrides, $overrides);

        return $this;
    }

    /**
     * Queue the token-endpoint responses the league provider will receive.
     */
    public function grants(PsrResponse ...$responses): self
    {
        $this->grants = array_merge($this->grants, $responses);

        return $this;
    }

    public function usingStore(EncryptedCacheStore $store): self
    {
        $this->store = $store;

        return $this;
    }

    public function seed(TokenSet $token, string $key = 'mcp-auth:notion'): self
    {
        $this->store->put($key, $token->toArray());

        return $this;
    }

    public function handler(): OAuthHandler
    {
        if ($this->handler instanceof OAuthHandler) {
            return $this->handler;
        }

        $args = array_merge([
            'serverName' => 'notion',
            'mcpUrl' => 'https://mcp.example.com/mcp',
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'configuredScope' => null,
            'store' => $this->store,
            'discovery' => new AuthServerDiscovery,
        ], $this->overrides);

        if ($this->grants !== [] && ! array_key_exists('httpClient', $args)) {
            $args['httpClient'] = $this->guzzle();
        }

        return $this->handler = new OAuthHandler(...$args);
    }

    public function storedToken(string $key = 'mcp-auth:notion'): ?TokenSet
    {
        $data = $this->store->get($key);

        return $data === null ? null : TokenSet::fromArray($data);
    }

    public function grantCount(): int
    {
        return count($this->history);
    }

    /**
     * Decode the form-encoded body of a captured token-endpoint request.
     *
     * @return array<string, mixed>
     */
    public function grantBody(int $index = 0): array
    {
        parse_str((string) $this->history[$index]['request']->getBody(), $body);

        return $body;
    }

    private function guzzle(): GuzzleClient
    {
        $stack = HandlerStack::create(new MockHandler($this->grants));
        $stack->push(Middleware::history($this->history));

        return new GuzzleClient(['handler' => $stack]);
    }
}
