<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Laravel\Mcp\Client\Exceptions\OAuthException;

class OAuthClient
{
    protected string $resourceUrl;

    protected ?DiscoveryResult $discovered = null;

    public function __construct(
        string $resourceUrl,
        protected OAuthConfig $config,
        protected ?string $resourceMetadataUrl = null,
        protected ?string $challengeScope = null,
        protected AuthServerDiscovery $discovery = new AuthServerDiscovery,
    ) {
        $this->resourceUrl = $this->canonical($resourceUrl);
    }

    public function redirect(?string $returnTo = null): RedirectResponse
    {
        $discovered = $this->discover();
        $metadata = $discovered->server;

        if ($metadata->codeChallengeMethodsSupported !== [] && ! in_array('S256', $metadata->codeChallengeMethodsSupported, true)) {
            throw new OAuthException('The authorization server does not support the required S256 PKCE method.');
        }

        $clientId = $this->config->clientId;
        $clientSecret = $this->config->clientSecret;
        $redirectUri = $this->redirectUri();

        if ($clientId === null) {
            $registration = $this->register($metadata, $redirectUri);

            $clientId = $registration->clientId;
            $clientSecret = $registration->clientSecret;
        }

        $pkce = Pkce::generate();
        $state = Str::random(40);

        Session::put($this->sessionKey(), [
            'state' => $state,
            'verifier' => $pkce->verifier,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'token_endpoint' => $metadata->tokenEndpoint,
            'token_auth_method' => $this->resolveTokenAuthMethod($metadata, $clientSecret),
            'redirect_uri' => $redirectUri,
            'return_to' => $returnTo,
            'issuer' => $metadata->issuer,
            'iss_supported' => $metadata->authorizationResponseIssParameterSupported,
        ]);

        $authorizeUrl = Uri::of($metadata->authorizationEndpoint)->withQuery(array_filter([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
            'scope' => $this->resolveScope($discovered),
            'resource' => $this->resourceUrl,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        return new RedirectResponse((string) $authorizeUrl);
    }

    public function token(): TokenSet
    {
        $error = Request::query('error');

        if (is_string($error) && $error !== '') {
            $description = Request::query('error_description');

            throw new OAuthException(is_string($description) && $description !== ''
                ? "The authorization server returned an error [{$error}]: {$description}"
                : "The authorization server returned an error [{$error}].");
        }

        $code = Request::query('code');

        if (is_string($code) && $code !== '') {
            $state = Request::query('state');
            $iss = Request::query('iss');

            return $this->exchangeAuthorizationCode(
                $code,
                is_string($state) ? $state : '',
                is_string($iss) ? $iss : null,
            );
        }

        if ($this->config->clientId === null) {
            throw new OAuthException('A client_id is required for the client_credentials grant.');
        }

        $discovered = $this->discover();

        return $this->requestToken(
            $discovered->server->tokenEndpoint,
            [
                'grant_type' => 'client_credentials',
                'scope' => $this->resolveScope($discovered),
                'resource' => $this->resourceUrl,
            ],
            $this->config->clientId,
            $this->config->clientSecret,
            $this->resolveTokenAuthMethod($discovered->server, $this->config->clientSecret),
        );
    }

    public function refresh(string $refreshToken, ?string $clientId = null, ?string $clientSecret = null): TokenSet
    {
        $discovered = $this->discover();

        $clientId ??= $this->config->clientId;
        $clientSecret ??= $this->config->clientSecret;

        $token = $this->requestToken(
            $discovered->server->tokenEndpoint,
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'scope' => $this->resolveScope($discovered),
                'resource' => $this->resourceUrl,
            ],
            $clientId,
            $clientSecret,
            $this->resolveTokenAuthMethod($discovered->server, $clientSecret),
        );

        $token->clientId = $clientId;
        $token->clientSecret = $clientSecret;

        return $token;
    }

    protected function exchangeAuthorizationCode(string $code, string $state, ?string $iss): TokenSet
    {
        /** @var array<string, mixed>|null $stored */
        $stored = Session::get($this->sessionKey());

        if (! is_array($stored)) {
            throw new OAuthException('No pending OAuth authorization was found in the session.');
        }

        if (! is_string($stored['state'] ?? null) || ! hash_equals($stored['state'], $state)) {
            throw new OAuthException('The OAuth state parameter did not match. Possible CSRF attempt.');
        }

        $this->validateIssuer($stored, $iss);

        Session::forget($this->sessionKey());

        $clientId = (string) $stored['client_id'];
        $clientSecret = isset($stored['client_secret']) ? (string) $stored['client_secret'] : null;

        $token = $this->requestToken(
            (string) $stored['token_endpoint'],
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => (string) $stored['redirect_uri'],
                'code_verifier' => (string) $stored['verifier'],
                'resource' => $this->resourceUrl,
            ],
            $clientId,
            $clientSecret,
            (string) ($stored['token_auth_method'] ?? 'client_secret_post'),
        );

        $token->clientId = $clientId;
        $token->clientSecret = $clientSecret;

        return $token;
    }

    protected function register(AuthServerMetadata $metadata, string $redirectUri): ClientRegistration
    {
        if ($metadata->registrationEndpoint === null) {
            throw new OAuthException('No client_id was configured and the authorization server does not support dynamic client registration.');
        }

        return (new DynamicClientRegistration)->register(
            $metadata->registrationEndpoint,
            $redirectUri,
            $this->resolveScope($this->discover()),
            applicationType: $this->applicationType($redirectUri),
            tokenEndpointAuthMethod: $this->resolveTokenAuthMethod($metadata, 'confidential'),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function requestToken(string $tokenEndpoint, array $params, ?string $clientId, ?string $clientSecret, string $authMethod): TokenSet
    {
        $request = Http::asForm()->acceptJson();

        if ($authMethod === 'client_secret_basic') {
            $request = $request->withBasicAuth((string) $clientId, (string) $clientSecret);
        } else {
            if ($clientId !== null) {
                $params['client_id'] = $clientId;
            }

            if ($authMethod === 'client_secret_post' && $clientSecret !== null) {
                $params['client_secret'] = $clientSecret;
            }
        }

        $response = $request->post($tokenEndpoint, $this->withoutNulls($params));

        if (! $response->successful()) {
            throw new OAuthException("Token request to [{$tokenEndpoint}] failed with status [{$response->status()}].");
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['access_token'])) {
            throw new OAuthException('The token response did not include an access_token.');
        }

        return TokenSet::fromResponse($data);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function withoutNulls(array $params): array
    {
        return array_filter($params, static fn (mixed $value): bool => $value !== null);
    }

    protected function discover(): DiscoveryResult
    {
        return $this->discovered ??= $this->discovery->discover($this->resourceUrl, $this->resourceMetadataUrl);
    }

    protected function resolveScope(DiscoveryResult $discovered): ?string
    {
        if ($this->config->scope !== null) {
            return $this->config->scope;
        }

        if ($this->challengeScope !== null && $this->challengeScope !== '') {
            return $this->challengeScope;
        }

        if ($discovered->scopesSupported !== []) {
            return implode(' ', $discovered->scopesSupported);
        }

        return null;
    }

    protected function resolveTokenAuthMethod(AuthServerMetadata $metadata, ?string $clientSecret): string
    {
        if ($this->config->tokenEndpointAuthMethod !== null) {
            return $this->config->tokenEndpointAuthMethod;
        }

        if ($clientSecret === null || $clientSecret === '') {
            return 'none';
        }

        $supported = $metadata->tokenEndpointAuthMethodsSupported;

        if ($supported !== [] && ! in_array('client_secret_post', $supported, true) && in_array('client_secret_basic', $supported, true)) {
            return 'client_secret_basic';
        }

        return 'client_secret_post';
    }

    protected function applicationType(string $redirectUri): string
    {
        $host = parse_url($redirectUri, PHP_URL_HOST);

        if (is_string($host) && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return 'native';
        }

        return 'web';
    }

    protected function canonical(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        $path = rtrim($parts['path'] ?? '', '/');

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $origin.$path.$query;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    protected function validateIssuer(array $stored, ?string $iss): void
    {
        $expectedIssuer = is_string($stored['issuer'] ?? null) ? $stored['issuer'] : '';

        if ($iss !== null) {
            if ($expectedIssuer === '' || ! hash_equals($expectedIssuer, $iss)) {
                throw new OAuthException('The OAuth issuer (iss) parameter did not match the expected issuer. Possible mix-up attack.');
            }

            return;
        }

        if ($stored['iss_supported'] ?? false) {
            throw new OAuthException('The authorization response is missing the required iss parameter.');
        }
    }

    protected function redirectUri(): string
    {
        if ($this->config->redirectUri === null) {
            throw new OAuthException('A redirect URI is required. Pass redirectUri to withOauth().');
        }

        return $this->config->redirectUri;
    }

    protected function sessionKey(): string
    {
        return 'mcp.oauth.'.sha1($this->resourceUrl);
    }
}
