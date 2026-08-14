<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\WebClient;

class OAuthRouteRegistrar
{
    public static function url(string $name): string
    {
        $root = rtrim((string) config('app.url'), '/');

        return $root === '' ? route($name) : $root.route($name, [], false);
    }

    /**
     * @param  Closure(string, TokenSet): mixed|array{0: class-string, 1: string}  $handler
     * @param  array<int, string>|string  $middleware
     * @param  array<string, mixed>  $clientMetadata
     */
    public function register(
        string $client,
        Closure|array $handler,
        array|string $middleware = 'web',
        ?string $connectUri = null,
        ?string $callbackUri = null,
        ?string $clientMetadataUri = null,
        array $clientMetadata = [],
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

        $document = Router::get($clientMetadataUri ?? "mcp/oauth/{$client}/client-metadata.json", fn (): JsonResponse => (new JsonResponse([
            'client_name' => trim(config('app.name').' MCP Client'),
            'client_uri' => rtrim((string) config('app.url'), '/'),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            ...$clientMetadata,
            'client_id' => self::url("mcp.oauth.{$client}.client-metadata"),
            'redirect_uris' => [self::url("mcp.oauth.{$client}.callback")],
            'token_endpoint_auth_method' => 'none',
        ]))->setPublic()->setMaxAge(3600));

        assert($document instanceof Route);

        $document->name("mcp.oauth.{$client}.client-metadata");

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
