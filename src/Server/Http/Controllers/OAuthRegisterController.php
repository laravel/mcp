<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Server\OAuth\PassportClients;
use Laravel\Mcp\Server\OAuth\RedirectUriPolicy;
use Laravel\Mcp\Server\Registrar;
use Throwable;

class OAuthRegisterController
{
    /**
     * Register a new OAuth client for a third-party application.
     *
     * @throws BindingResolutionException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => ['nullable', 'string', 'min:1', 'max:255'],
            'name' => ['nullable', 'string', 'min:1', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['bail', 'required', 'string', 'max:1024', function (string $attribute, $value, $fail): void {
                if (! $this->isValidRedirectUri($value)) {
                    $fail($attribute.' is not a valid URL.');

                    return;
                }

                if (! $this->redirectUriPolicy()->isPermitted($value)) {
                    $fail($attribute.' is not a permitted redirect domain.');
                }
            }],
            'logo_uri' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'client_uri' => ['nullable', 'string', 'url:http,https', 'max:2048'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $redirectError = $errors->first('redirect_uris*');

            return response()->json([
                'error' => $redirectError !== '' ? 'invalid_redirect_uri' : 'invalid_client_metadata',
                'error_description' => $redirectError !== '' ? $redirectError : $errors->first(),
            ], 400);
        }

        $validated = $validator->validated();

        if (class_exists('Laravel\Passport\ClientRepository') === false) {
            return response()->json([
                'error' => 'server_error',
                'error_description' => 'OAuth support (Passport) is not installed.',
            ], 500);
        }

        $clients = Container::getInstance()->make(
            'Laravel\Passport\ClientRepository'
        );

        try {
            $client = $clients->createAuthorizationCodeGrantClient(
                name: $this->resolveClientName($validated),
                redirectUris: $validated['redirect_uris'],
                confidential: false,
                enableDeviceFlow: false,
            );

            $this->grantMcpScope($client);

            $metadata = $this->persistClientMetadata($client, $validated);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'The client could not be registered.',
            ], 500);
        }

        return response()->json([
            'client_id' => (string) $client->id,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'redirect_uris' => $client->redirect_uris,
            'scope' => Registrar::OAUTH_SCOPE,
            'token_endpoint_auth_method' => 'none',
            ...$metadata,
        ], 201);
    }

    protected function grantMcpScope(mixed $client): void
    {
        $this->passportClients()->grantMcpScope($client);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function persistClientMetadata(mixed $client, array $validated): array
    {
        return $this->passportClients()->persistMetadata($client, $validated);
    }

    /**
     * Resolve the client name, falling back to the redirect host or a default.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveClientName(array $validated): string
    {
        return $validated['client_name']
            ?? $validated['name']
            ?? (parse_url((string) ($validated['redirect_uris'][0] ?? ''), PHP_URL_HOST) ?: 'MCP Client');
    }

    protected function isValidRedirectUri(string $value): bool
    {
        return $this->redirectUriPolicy()->isValid($value);
    }

    protected function redirectUriPolicy(): RedirectUriPolicy
    {
        return RedirectUriPolicy::fromConfig();
    }

    protected function passportClients(): PassportClients
    {
        return new PassportClients;
    }
}
