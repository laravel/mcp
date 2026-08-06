<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\WebClient;

class OAuthRouteRegistrar
{
    /**
     * @param  Closure(string, TokenSet): mixed|array{0: class-string, 1: string}  $handler
     * @param  array<int, string>|string  $middleware
     */
    public function register(
        string $client,
        Closure|array $handler,
        array|string $middleware = 'web',
        ?string $connectUri = null,
        ?string $callbackUri = null,
    ): void {
        if (is_array($handler)) {
            $handler = $handler[0].'@'.$handler[1];
        }

        $connect = Router::get($connectUri ?? "mcp/{$client}/connect", function () use ($client): mixed {
            $resourceMetadata = request()->query('resource_metadata');
            $scope = request()->query('scope');

            return $this->webClient($client)->oAuthClient(
                is_string($resourceMetadata) && $resourceMetadata !== '' ? $resourceMetadata : null,
                is_string($scope) && $scope !== '' ? $scope : null,
            )->redirect();
        });

        assert($connect instanceof Route);

        $connect->name("mcp.oauth.{$client}.connect")->middleware($middleware);

        $callback = Router::get($callbackUri ?? "mcp/oauth/{$client}/callback", function () use ($client, $handler): mixed {
            $oauth = $this->webClient($client)->oAuthClient();
            $token = $oauth->exchangeCallback();

            $result = Container::getInstance()->call($handler, [
                'provider' => $client,
                'client' => $client,
                'token' => $token,
                'returnTo' => $oauth->returnTo(),
            ]);

            return $result ?? redirect($oauth->returnTo() ?? '/');
        });

        assert($callback instanceof Route);

        $callback->name("mcp.oauth.{$client}.callback")->middleware($middleware);

        Router::getRoutes()->refreshNameLookups();
    }

    protected function webClient(string $name): WebClient
    {
        $client = Container::getInstance()->make(ClientManager::class)->client($name);

        if (! $client instanceof WebClient) {
            throw new ClientException("MCP client [{$name}] does not support OAuth.");
        }

        return $client;
    }
}
