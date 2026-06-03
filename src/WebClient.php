<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\OAuthClient;
use Laravel\Mcp\Client\OAuth\OAuthConfig;
use Laravel\Mcp\Client\OAuth\TokenEndpointAuthMethod;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Schema\Implementation;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class WebClient extends Client
{
    protected ?OAuthConfig $oAuthConfig = null;

    public function __construct(
        protected HttpTransport $httpTransport,
        ?Implementation $clientInfo = null,
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

    public function withOAuth(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $scope = 'mcp:use',
        ?string $redirectUri = null,
        ?TokenEndpointAuthMethod $tokenEndpointAuthMethod = null,
    ): static {
        $this->oAuthConfig = new OAuthConfig(
            clientId: $clientId,
            clientSecret: $clientSecret,
            scope: $scope,
            redirectUri: $redirectUri,
            tokenEndpointAuthMethod: $tokenEndpointAuthMethod,
        );

        return $this;
    }

    public function oAuth(?string $resourceMetadataUrl = null, ?string $challengeScope = null): OAuthClient
    {
        if (! $this->oAuthConfig instanceof OAuthConfig) {
            throw new OAuthException('No OAuth configuration found. Call withOAuth() before oAuth().');
        }

        if ($this->oAuthConfig->redirectUri === null) {
            $this->oAuthConfig->redirectUri = $this->defaultRedirectUri();
        }

        return new OAuthClient($this->httpTransport->url(), $this->oAuthConfig, $resourceMetadataUrl, $challengeScope);
    }

    protected function defaultRedirectUri(): ?string
    {
        if ($this->registeredName === null) {
            return null;
        }

        try {
            return route("mcp.oauth.{$this->registeredName}.callback");
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
