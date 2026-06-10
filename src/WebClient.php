<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\OAuthClient;
use Laravel\Mcp\Client\OAuth\OAuthConfig;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Schema\Implementation;
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
    public function withToken(string|Closure $token): static
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
        ?string $clientSecret = null,
        ?string $scope = null,
        ?string $redirectUri = null,
    ): static {
        $this->oAuthConfig = new OAuthConfig(
            clientId: $clientId,
            clientSecret: $clientSecret,
            scope: $scope,
            redirectUri: $redirectUri,
        );

        return $this;
    }

    public function oAuthClient(?string $resourceMetadataUrl = null, ?string $challengeScope = null): OAuthClient
    {
        if (! $this->oAuthConfig instanceof OAuthConfig) {
            throw new OAuthException('No OAuth configuration found. Call withOAuth() before oAuthClient().');
        }

        $config = $this->oAuthConfig;

        if ($config->redirectUri === null && $this->name !== null) {
            $config = clone $config;

            try {
                $config->redirectUri = route("mcp.oauth.{$this->name}.callback");
            } catch (RouteNotFoundException) {
            }
        }

        return new OAuthClient($config, $this->httpTransport->url(), $resourceMetadataUrl, $challengeScope);
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
