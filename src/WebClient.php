<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\OAuthClient;
use Laravel\Mcp\Client\OAuth\OAuthConfig;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Schema\Implementation;
use SensitiveParameter;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class WebClient extends Client
{
    public function __construct(
        protected HttpTransport $httpTransport,
        public ?Implementation $clientInfo = null,
        protected ?OAuthConfig $oAuthConfig = null,
    ) {
        parent::__construct($httpTransport, $clientInfo);
    }

    /**
     * @param  string|Closure(): string  $token
     */
    public function withToken(#[SensitiveParameter] string|Closure $token): static
    {
        $this->httpTransport->withToken($token);

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $this->httpTransport->withHeaders($headers);

        return $this;
    }

    public function withOAuth(
        ?string $clientId = null,
        #[SensitiveParameter]
        ?string $clientSecret = null,
        ?string $scope = null,
        ?string $redirectUri = null,
        ?string $clientIdMetadataUrl = null,
    ): static {
        $this->oAuthConfig = new OAuthConfig(
            clientId: $clientId,
            clientSecret: $clientSecret,
            scope: $scope,
            redirectUri: $redirectUri,
            clientIdMetadataUrl: $clientIdMetadataUrl,
        );

        return $this;
    }

    public function oAuthClient(?string $resourceMetadataUrl = null, ?string $challengeScope = null): OAuthClient
    {
        if (! $this->oAuthConfig instanceof OAuthConfig) {
            throw new OAuthException('No OAuth configuration found. Call withOAuth() before oAuthClient().');
        }

        $config = $this->oAuthConfig;

        if ($this->name !== null && ($config->redirectUri === null || $config->clientIdMetadataUrl === null)) {
            $config = clone $config;

            $config->redirectUri ??= $this->routeUrl("mcp.oauth.{$this->name}.callback");
            $config->clientIdMetadataUrl ??= $this->routeUrl("mcp.oauth.{$this->name}.client-metadata");
        }

        return new OAuthClient($config, $this->httpTransport->url(), $resourceMetadataUrl, $challengeScope);
    }

    protected function routeUrl(string $name): ?string
    {
        try {
            return route($name);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);

        $this->oAuthConfig = null;

        if ($this->transport instanceof HttpTransport) {
            $this->httpTransport = $this->transport;
        }
    }
}
