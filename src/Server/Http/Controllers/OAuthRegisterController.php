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

        $clients = Container::getInstance()->make(
            "Laravel\Passport\ClientRepository"
        );

        $client = $clients->createAuthorizationCodeGrantClient(
            name: $request->get('client_name', $request->get('name')),
            redirectUris: $validated['redirect_uris'],
            confidential: false,
            user: null,
            enableDeviceFlow: false,
        );

        return response()->json([
            'client_id' => (string) $client->id,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'redirect_uris' => $client->redirect_uris,
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ]);
    }

    /**
     * Determine if the given URL targets a loopback address over HTTP.
     */
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
