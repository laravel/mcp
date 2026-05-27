<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Exceptions\OAuthException;
use Throwable;

class DynamicClientRegistration
{
    /**
     * @param  array{redirect_uris: array<int, string>, scope?: ?string, public_client?: bool}  $request
     */
    public function register(string $registrationEndpoint, array $request): ClientRegistration
    {
        $payload = [
            'client_name' => (string) config('app.name', 'Laravel MCP Client'),
            'application_type' => 'web',
            'redirect_uris' => $request['redirect_uris'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => ($request['public_client'] ?? true) ? 'none' : 'client_secret_basic',
        ];

        if (isset($request['scope']) && $request['scope'] !== '') {
            $payload['scope'] = $request['scope'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->post($registrationEndpoint, $payload);
        } catch (Throwable $throwable) {
            throw new OAuthException("Dynamic client registration to [{$registrationEndpoint}] failed: {$throwable->getMessage()}.", $throwable->getCode(), $throwable);
        }

        if (! $response->successful()) {
            throw new OAuthException("Dynamic client registration to [{$registrationEndpoint}] returned HTTP [{$response->status()}]: {$response->body()}");
        }

        try {
            $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new OAuthException("Dynamic client registration response from [{$registrationEndpoint}] is not valid JSON.");
        }

        if (! is_array($data) || ! isset($data['client_id']) || $data['client_id'] === '') {
            throw new OAuthException("Dynamic client registration response from [{$registrationEndpoint}] is missing [client_id].");
        }

        /** @var array<string, mixed> $data */
        return ClientRegistration::fromArray($data);
    }
}
