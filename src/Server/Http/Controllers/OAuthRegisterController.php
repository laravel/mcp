<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OAuthRegisterController
{
    /**
     * Register a new OAuth client for a third-party application.
     *
     * @throws BindingResolutionException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url', function (string $attribute, $value, $fail): void {
                if (config('mcp.allow_all_redirect_domains')) {
                    return;
                }

                if (! Str::startsWith($value, $this->allowedDomains())) {
                    $fail($attribute.' must be an allowed domain.');
                }
            }],
        ]);

        $clients = Container::getInstance()->make(
            "Laravel\Passport\ClientRepository"
        );

        $client = $clients->createAuthorizationCodeGrantClient(
            name: $request->get('name'),
            redirectUris: $validated['redirect_uris'],
            confidential: false,
            user: null,
            enableDeviceFlow: false,
        );

        return response()->json([
            'client_id' => $client->id,
            'grant_types' => $client->grantTypes,
            'response_types' => ['code'],
            'redirect_uris' => $client->redirectUris,
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ]);
    }

    /**
     * @return array<string>
     */
    protected function allowedDomains(): array
    {
        /** @var array<string> $allowedDomains */
        $allowedDomains = config('mcp.allowed_redirect_domains', []);

        // Check if each domain ends in a slash, if not add it
        return collect($allowedDomains)
            ->map(fn (string $domain): string => Str::endsWith($domain, '/')
                ? $domain
                : "{$domain}/"
            )
            ->toArray();
    }
}
