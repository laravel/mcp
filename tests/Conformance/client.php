<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\OAuthClient;
use Laravel\Mcp\Client\OAuth\OAuthConfig;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Orchestra\Testbench\Foundation\Application;

$base = dirname(__DIR__, 2);

require_once $base.'/vendor/autoload.php';

$url = $argv[1] ?? null;
$scenario = getenv('MCP_CONFORMANCE_SCENARIO') ?: null;

if ($url === null || $scenario === null) {
    fwrite(STDERR, "Usage: MCP_CONFORMANCE_SCENARIO=<scenario> php client.php <server-url>\n");

    exit(1);
}

$app = Application::create(basePath: $base.'/vendor/orchestra/testbench-core/laravel');

$app['config']->set('session.driver', 'array');

$log = function (string $message) use ($scenario): void {
    file_put_contents(
        __DIR__.'/logs/client.log',
        sprintf("[%s] %s\n", $scenario, $message),
        FILE_APPEND,
    );
};

@mkdir(__DIR__.'/logs', 0777, true);

$log(sprintf('start url=%s', $url));

$context = json_decode(getenv('MCP_CONFORMANCE_CONTEXT') ?: '{}', true) ?: [];

$token = null;

$client = Client::web($url)
    ->withTimeout(30)
    ->withToken(function () use (&$token): ?string {
        return $token?->accessToken;
    });

$authorize = function (AuthorizationRequiredException $exception) use ($url, $scenario, $context, $log): TokenSet {
    // The suite advertises a client ID metadata document on a host that the
    // authorization server never fetches, so skip the public host check.
    $oauth = new class(new OAuthConfig(clientId: $context['client_id'] ?? null, clientSecret: $context['client_secret'] ?? null, redirectUri: 'http://localhost/callback'), $url, $exception->resourceMetadataUrl(), $exception->scope(), clientIdMetadataUrl: 'https://conformance-test.local/client-metadata.json') extends OAuthClient
    {
        protected function isPubliclyResolvable(string $host): bool
        {
            return true;
        }
    };

    if ($scenario === 'auth/client-credentials-basic') {
        $log('requesting client_credentials token');

        return $oauth->clientCredentials();
    }

    $authorizeUrl = $oauth->redirect()->getTargetUrl();

    parse_str((string) parse_url($authorizeUrl, PHP_URL_QUERY), $authorizeQuery);

    $log(sprintf('authorizing with scope [%s]', $authorizeQuery['scope'] ?? ''));

    $location = Http::withOptions(['allow_redirects' => false])->get($authorizeUrl)->header('Location');

    if ($location === '') {
        throw new OAuthException('The authorization endpoint did not redirect back to the client.');
    }

    parse_str((string) parse_url($location, PHP_URL_QUERY), $callbackQuery);

    request()->query->replace($callbackQuery);

    return $oauth->exchangeCallback();
};

$run = function () use ($client, $scenario, $context, $log): void {
    $client->connect();

    $log(sprintf('connected using %s', $client->protocolVersion()->value));

    $tools = $client->tools();

    $log(sprintf('listed %d tools', $tools->count()));

    $calls = match (true) {
        isset($context['toolCalls']) => $context['toolCalls'],
        $scenario === 'tools_call' => [['name' => 'add_numbers', 'arguments' => ['a' => 1, 'b' => 2]]],
        $scenario === 'json-schema-2020-12-preservation' => [[
            'name' => 'json_schema_echo',
            'arguments' => ['schema' => $tools->firstWhere('name', 'json_schema_2020_12_tool')?->inputSchema],
        ]],
        $scenario === 'initialize' || $tools->isEmpty() => [],
        default => [['name' => $tools->first()->name, 'arguments' => []]],
    };

    foreach ($calls as $call) {
        $client->callTool($tools->firstWhere('name', $call['name']) ?? $call['name'], $call['arguments'] ?? []);

        $log(sprintf('called tool %s', $call['name']));
    }
};

try {
    for ($attempt = 1; ; $attempt++) {
        try {
            $run();

            break;
        } catch (AuthorizationRequiredException $exception) {
            if ($attempt > 3) {
                throw $exception;
            }

            $log(sprintf('authorization required: %s', $exception->getMessage()));

            $token = $authorize($exception);
        }
    }

    $client->disconnect();

    $log('disconnected');

    exit(0);
} catch (Throwable $throwable) {
    $log(sprintf('error: %s', $throwable->getMessage()));

    fwrite(STDERR, sprintf("Error: %s\n%s\n", $throwable->getMessage(), $throwable->getTraceAsString()));

    try {
        $client->disconnect();
    } catch (Throwable) {
        //
    }

    exit(1);
}
