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

class OAuthClientConnectController extends Controller
{
    public function __invoke(Request $request, ClientManager $clients, string $server): RedirectResponse
    {
        try {
            $client = $clients->client($server);
        } catch (ClientException) {
            throw new NotFoundHttpException("MCP client [{$server}] is not registered.");
        }

        if (! $client instanceof WebClient) {
            throw new NotFoundHttpException("MCP client [{$server}] is not an HTTP client.");
        }

        $handler = $client->oauthHandlerForRoutes();

        if (! $handler->isAuthorizationCode()) {
            throw new NotFoundHttpException("MCP client [{$server}] is not configured for authorization_code OAuth.");
        }

        $intended = $request->query('intended');

        try {
            $redirect = $handler->startAuthorization(is_string($intended) ? $intended : null);
        } catch (OAuthException $oAuthException) {
            session()->flash('mcp.oauth.error', $oAuthException->getMessage());

            return redirect()->to($this->errorUrl());
        }

        return redirect()->away($redirect->url);
    }

    protected function errorUrl(): string
    {
        return (string) (config('mcp.oauth.error_url') ?? '/');
    }
}
