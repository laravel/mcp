<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

function resultFor(array $message, ?Closure $factory = null): array
{
    $transport = new ArrayTransport;
    $server = $factory instanceof Closure ? $factory($transport) : new ExampleServer($transport);

    $server->start();

    ($transport->handler)((string) json_encode($message));

    return json_decode((string) $transport->sent[0], true);
}

it('adds caching hints to every cacheable result', function (string $method, array $params): array {
    $result = resultFor([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => ['_meta' => protocolMeta(), ...$params],
    ])['result'];

    expect($result['ttlMs'])->toBe(0);
    expect($result['cacheScope'])->toBe('private');

    return $result;
})->with([
    'server/discover' => ['server/discover', []],
    'tools/list' => ['tools/list', []],
    'prompts/list' => ['prompts/list', []],
    'resources/list' => ['resources/list', []],
    'resources/templates/list' => ['resources/templates/list', []],
    'resources/read' => ['resources/read', ['uri' => 'file://resources/last-log-line-resource']],
]);

it('leaves caching hints off a result that is not cacheable', function (): void {
    $result = resultFor(callToolMessage())['result'];

    expect($result)->not->toHaveKey('ttlMs');
    expect($result)->not->toHaveKey('cacheScope');
});

it('uses the ttl and scope the server declares', function (): void {
    $result = resultFor(listToolsMessage(), fn (ArrayTransport $transport): Server => new class($transport) extends ExampleServer
    {
        protected int $cacheTtlMs = 300000;

        protected CacheScope $cacheScope = CacheScope::PUBLIC;
    })['result'];

    expect($result['ttlMs'])->toBe(300000);
    expect($result['cacheScope'])->toBe('public');
});

it('never emits a negative ttl', function (): void {
    $result = resultFor(listToolsMessage(), fn (ArrayTransport $transport): Server => new class($transport) extends ExampleServer
    {
        protected int $cacheTtlMs = -1;
    })['result'];

    expect($result['ttlMs'])->toBe(0);
});

it('returns tools in the same order on every request', function (): void {
    $names = fn (): array => array_column(resultFor(listToolsMessage())['result']['tools'], 'name');

    expect($names())->toBe($names())->and($names())->not->toBeEmpty();
});
