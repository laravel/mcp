<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Orchestra\Testbench\Foundation\Application;

$base = dirname(__DIR__, 2);

require_once $base.'/vendor/autoload.php';

$url = $argv[1] ?? null;
$scenario = getenv('MCP_CONFORMANCE_SCENARIO') ?: null;

if ($url === null || $scenario === null) {
    fwrite(STDERR, "Usage: MCP_CONFORMANCE_SCENARIO=<scenario> php client.php <server-url>\n");

    exit(1);
}

Application::create(basePath: $base.'/vendor/orchestra/testbench-core/laravel');

$log = function (string $message) use ($scenario): void {
    file_put_contents(
        __DIR__.'/logs/client.log',
        sprintf("[%s] %s\n", $scenario, $message),
        FILE_APPEND,
    );
};

@mkdir(__DIR__.'/logs', 0777, true);

$log(sprintf('start url=%s', $url));

$client = Client::web($url)->withTimeout(30);

try {
    $client->connect();

    $log(sprintf('connected using %s', $client->protocolVersion()->value));

    $tools = $client->tools();

    $log(sprintf('listed %d tools', $tools->count()));

    $context = json_decode(getenv('MCP_CONFORMANCE_CONTEXT') ?: '{}', true) ?: [];

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
