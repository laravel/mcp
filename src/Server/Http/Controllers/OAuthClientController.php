<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\OAuthException;
use Laravel\Mcp\WebClient;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OAuthClientController extends Controller
{
    public function connect(Request $request, ClientManager $clients, string $server): RedirectResponse
    {
        $client = $this->resolveConsentClient($clients, $server);

        $intended = $request->query('intended');

        try {
            $redirect = $client->startAuthorization(is_string($intended) ? $intended : null);
        } catch (OAuthException $oAuthException) {
            return $this->flashErrorAndRedirect($oAuthException->getMessage());
        }

        return redirect()->away($redirect->url);
    }

    public function callback(Request $request, ClientManager $clients, string $server): RedirectResponse
    {
        $client = $this->resolveWebClient($clients, $server);

        if ($request->filled('error')) {
            return $this->flashErrorAndRedirect($this->resolveErrorMessage($request));
        }

        $code = $request->query('code');
        $state = $request->query('state');

        if (! is_string($code) || ! is_string($state) || $code === '' || $state === '') {
            return $this->flashErrorAndRedirect('OAuth callback is missing the code or state parameter.');
        }

        try {
            $client->completeAuthorization($code, $state);
        } catch (OAuthException $oAuthException) {
            return $this->flashErrorAndRedirect($oAuthException->getMessage());
        }

        return redirect()->to($this->successUrl($client->lastIntendedUrl()));
    }

    protected function resolveWebClient(ClientManager $clients, string $server): WebClient
    {
        try {
            $client = $clients->client($server)->client();
        } catch (ClientException) {
            throw new NotFoundHttpException("MCP client [{$server}] is not registered.");
        }

        if (! $client instanceof WebClient) {
            throw new NotFoundHttpException("MCP client [{$server}] is not an HTTP client.");
        }

        return $client;
    }

    protected function resolveConsentClient(ClientManager $clients, string $server): WebClient
    {
        $client = $this->resolveWebClient($clients, $server);

        if (! $client->requiresUserConsent()) {
            throw new NotFoundHttpException("MCP client [{$server}] does not require user consent.");
        }

        return $client;
    }

    protected function resolveErrorMessage(Request $request): string
    {
        $description = $request->query('error_description');

        if (is_string($description) && $description !== '') {
            return $description;
        }

        $code = $request->query('error');

        return is_string($code) ? $code : 'OAuth authorization failed.';
    }

    protected function flashErrorAndRedirect(string $message): RedirectResponse
    {
        session()->flash('mcp.oauth.error', $message);

        return redirect()->to($this->errorUrl());
    }

    protected function successUrl(?string $intended): string
    {
        if ($intended !== null && $intended !== '' && $this->isSafeRedirectTarget($intended)) {
            return $intended;
        }

        return (string) (config('mcp.client.oauth.success_url') ?? '/');
    }

    protected function isSafeRedirectTarget(string $url): bool
    {
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host === request()->getHost();
    }

    protected function errorUrl(): string
    {
        return (string) (config('mcp.client.oauth.error_url') ?? '/');
    }
}
