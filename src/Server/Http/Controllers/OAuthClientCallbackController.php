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

class OAuthClientCallbackController extends Controller
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

        if ($request->filled('error')) {
            $errorDescription = $request->query('error_description');
            $errorCode = $request->query('error');
            $message = is_string($errorDescription) && $errorDescription !== ''
                ? $errorDescription
                : (is_string($errorCode) ? $errorCode : 'OAuth authorization failed.');

            session()->flash('mcp.oauth.error', $message);

            return redirect()->to($this->errorUrl());
        }

        $code = $request->query('code');
        $state = $request->query('state');

        if (! is_string($code) || ! is_string($state) || $code === '' || $state === '') {
            session()->flash('mcp.oauth.error', 'OAuth callback is missing the code or state parameter.');

            return redirect()->to($this->errorUrl());
        }

        $handler = $client->oauthHandlerForRoutes();

        try {
            $handler->completeAuthorization($code, $state);
        } catch (OAuthException $oAuthException) {
            session()->flash('mcp.oauth.error', $oAuthException->getMessage());

            return redirect()->to($this->errorUrl());
        }

        return redirect()->to($this->successUrl());
    }

    protected function successUrl(): string
    {
        $intended = session()->pull('mcp.oauth.intended');

        if (is_string($intended) && $intended !== '') {
            return $intended;
        }

        return (string) (config('mcp.oauth.success_url') ?? '/');
    }

    protected function errorUrl(): string
    {
        return (string) (config('mcp.oauth.error_url') ?? '/');
    }
}
