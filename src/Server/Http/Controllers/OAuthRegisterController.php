<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Registrar;
use RuntimeException;
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
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['bail', 'required', 'string', function (string $attribute, $value, $fail): void {
                if (! $this->isValidRedirectUri($value)) {
                    $fail($attribute.' is not a valid URL.');

                    return;
                }

                if (! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    return;
                }

                if (in_array('*', config('mcp.redirect_domains', []), true)) {
                    return;
                }

                if ($this->hasLocalhostDomain() && $this->isLocalhostUrl($value)) {
                    return;
                }

                if (! Str::startsWith($value, $this->allowedDomains())) {
                    $fail($attribute.' is not a permitted redirect domain.');
                }
            }],
        ]);

        // Preserve error precedence by adding metadata rules after redirect rules are expanded.
        $validator->addRules([
            'logo_uri' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'client_uri' => ['nullable', 'string', 'url:http,https', 'max:2048'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            $isRedirectError = collect($errors->keys())->contains(
                fn (string $key): bool => str_starts_with($key, 'redirect_uris')
            );

            return response()->json([
                'error' => $isRedirectError ? 'invalid_redirect_uri' : 'invalid_client_metadata',
                'error_description' => $errors->first(),
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
            $passport = 'Laravel\Passport\Passport';

            /** @var Model $model */
            $model = $passport::client();

            [$client, $metadata] = $model->getConnection()->transaction(function () use ($clients, $validated): array {
                $client = $clients->createAuthorizationCodeGrantClient(
                    name: $this->resolveClientName($validated),
                    redirectUris: $validated['redirect_uris'],
                    confidential: false,
                    enableDeviceFlow: false,
                );

                $this->grantMcpScope($client);

                return [$client, $this->persistClientMetadata($client, $validated)];
            });
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
        if (! $client instanceof Model) {
            return;
        }

        $scopes = $client->refresh()->getAttribute('scopes');

        if (! is_array($scopes) || in_array(Registrar::OAUTH_SCOPE, $scopes, true)) {
            return;
        }

        $client->forceFill(['scopes' => [...$scopes, Registrar::OAUTH_SCOPE]])->save();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function persistClientMetadata(mixed $client, array $validated): array
    {
        if (! $client instanceof Model) {
            return [];
        }

        $columns = $client->getConnection()->getSchemaBuilder()->getColumnListing($client->getTable());
        $supported = array_intersect(['logo_uri', 'client_uri'], $columns);
        $metadata = array_intersect_key($validated, array_flip($supported));

        if ($metadata !== []) {
            if (! $client->forceFill($metadata)->save()) {
                throw new RuntimeException('The client metadata could not be saved.');
            }

            $client->refresh();
        }

        $metadata = [];

        foreach ($supported as $attribute) {
            if (($value = $client->getAttribute($attribute)) !== null) {
                $metadata[$attribute] = $value;
            }
        }

        return $metadata;
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
        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            return Str::isUrl($value, ['http', 'https']);
        }

        /** @var array<int, string> */
        $allowedSchemes = config('mcp.custom_schemes', []);
        $host = parse_url($value, PHP_URL_HOST);

        return in_array($scheme, $allowedSchemes, true) && is_string($host) && $host !== '';
    }

    protected function isLocalhostUrl(string $url): bool
    {
        return Str::startsWith($url, [
            'http://localhost:',
            'http://localhost/',
            'http://127.0.0.1:',
            'http://127.0.0.1/',
            'http://[::1]:',
            'http://[::1]/',
        ]);
    }

    /**
     * Get the allowed redirect domains.
     *
     * @return array<int, string>
     */
    protected function allowedDomains(): array
    {
        /** @var array<int, string> */
        $allowedDomains = config('mcp.redirect_domains', []);

        return collect($allowedDomains)
            ->map(fn (string $domain): string => Str::endsWith($domain, '/')
                ? $domain
                : "{$domain}/"
            )
            ->all();
    }

    private function hasLocalhostDomain(): bool
    {
        /** @var array<int, string> */
        $domains = config('mcp.redirect_domains', []);

        return collect($domains)->contains(fn (string $domain): bool => in_array(
            rtrim(Str::after($domain, '://'), '/'),
            ['localhost', '127.0.0.1', '[::1]'],
            true,
        ));
    }
}
