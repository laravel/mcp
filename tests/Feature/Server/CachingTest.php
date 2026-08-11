<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\CacheableResource;
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

it('leaves caching hints off a request retried with input responses', function (array $params): void {
    $result = resultFor([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => [
            '_meta' => protocolMeta(),
            'uri' => 'file://resources/last-log-line-resource',
            ...$params,
        ],
    ])['result'];

    expect($result)->not->toHaveKey('ttlMs');
    expect($result)->not->toHaveKey('cacheScope');
})->with([
    'inputResponses' => [['inputResponses' => ['login' => ['action' => 'accept']]]],
    'requestState' => [['requestState' => 'eyJ...']],
]);

it('uses the hint the server attribute declares', function (): void {
    $result = resultFor(listToolsMessage(), fn (ArrayTransport $transport): Server => new #[Cacheable(ttlMs: 300000, scope: CacheScope::PUBLIC)] class($transport) extends ExampleServer {})['result'];

    expect($result['ttlMs'])->toBe(300000);
    expect($result['cacheScope'])->toBe('public');
});

it('prefers the per method hint over the server attribute', function (): void {
    $server = fn (ArrayTransport $transport): Server => new #[Cacheable(ttlMs: 300000, scope: CacheScope::PUBLIC)] class($transport) extends ExampleServer
    {
        protected function cacheHints(): array
        {
            return ['tools/list' => new Cacheable(ttlMs: 60000)];
        }
    };

    expect(resultFor(listToolsMessage(), $server)['result'])
        ->ttlMs->toBe(60000)
        ->cacheScope->toBe('private');

    $listPrompts = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'prompts/list', 'params' => ['_meta' => protocolMeta()]];

    expect(resultFor($listPrompts, $server)['result'])
        ->ttlMs->toBe(300000)
        ->cacheScope->toBe('public');
});

it('prefers the resource attribute over the server hints', function (): void {
    $server = function (ArrayTransport $transport): Server {
        $server = new #[Cacheable(ttlMs: 300000)] class($transport) extends ExampleServer
        {
            protected function cacheHints(): array
            {
                return ['resources/read' => new Cacheable(ttlMs: 60000)];
            }
        };

        $server->resources[] = CacheableResource::class;

        return $server;
    };

    expect(resultFor(readResourceMessage('file://resources/cacheable-resource'), $server)['result'])
        ->ttlMs->toBe(120000)
        ->cacheScope->toBe('public');

    expect(resultFor(readResourceMessage('file://resources/last-log-line-resource'), $server)['result'])
        ->ttlMs->toBe(60000)
        ->cacheScope->toBe('private');
});

it('never emits a negative ttl', function (): void {
    $result = resultFor(listToolsMessage(), fn (ArrayTransport $transport): Server => new #[Cacheable(ttlMs: -1)] class($transport) extends ExampleServer {})['result'];

    expect($result['ttlMs'])->toBe(0);
});

it('returns tools in the same order on every request', function (): void {
    $names = fn (): array => array_column(resultFor(listToolsMessage())['result']['tools'], 'name');

    expect($names())->toBe($names())->and($names())->not->toBeEmpty();
});
