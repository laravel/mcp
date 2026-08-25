<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Client\ResponseCache;
use Tests\Fixtures\Client\FakeTransport;

function resourceResponse(string $text, string $resultType = 'complete'): string
{
    return (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resultType' => $resultType,
            'ttlMs' => 300000,
            'cacheScope' => 'private',
            'contents' => [['uri' => 'file://a', 'mimeType' => 'text/plain', 'text' => $text]],
        ],
    ]);
}

function toolsPage(string $tool, ?string $nextCursor = null): string
{
    return (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => array_filter([
            'resultType' => 'complete',
            'ttlMs' => 300000,
            'cacheScope' => 'private',
            'tools' => [['name' => $tool]],
            'nextCursor' => $nextCursor,
        ]),
    ]);
}

function cacheableTransport(int $ttlMs = 300000, string $scope = 'private'): FakeTransport
{
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resultType' => 'complete',
            'ttlMs' => $ttlMs,
            'cacheScope' => $scope,
            'tools' => [['name' => 'add']],
        ],
    ]);

    return $transport;
}

it('serves a fresh result without touching the transport again', function (): void {
    $transport = cacheableTransport();
    $client = (new Client($transport))->withCache();

    expect($client->tools()->keys()->all())->toBe(['add']);

    $sent = count($transport->sent);

    expect($client->tools()->keys()->all())->toBe(['add'])
        ->and($transport->sent)->toHaveCount($sent);
});

it('re-fetches once the ttl has elapsed', function (): void {
    $transport = cacheableTransport(ttlMs: 1000);
    $client = (new Client($transport))->withCache();

    $client->tools();

    $sent = count($transport->sent);

    $this->travel(2)->seconds();
    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('never caches a result the server marked immediately stale', function (): void {
    $transport = cacheableTransport(ttlMs: 0);
    $client = (new Client($transport))->withCache();

    $client->tools();

    $sent = count($transport->sent);

    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('keeps private results out of another authorization context', function (): void {
    (new Client(cacheableTransport()))->withCache(context: 'tenant-a')->tools();

    $second = cacheableTransport();
    (new Client($second))->withCache(context: 'tenant-b')->tools();

    expect($second->sent)->toContain(fn (string $frame): bool => str_contains($frame, 'tools/list'));
});

it('shares public results across authorization contexts', function (): void {
    (new Client(cacheableTransport(scope: 'public')))->withCache(context: 'tenant-a')->tools();

    $second = cacheableTransport(scope: 'public');
    $tools = (new Client($second))->withCache(context: 'tenant-b')->tools();

    expect($tools->keys()->all())->toBe(['add'])
        ->and($second->sent)->toBeEmpty();
});

it('never resolves the transport recipe for an uncacheable method', function (): void {
    $transport = new class extends FakeTransport
    {
        public int $recipes = 0;

        public function recipe(): array
        {
            $this->recipes++;

            return parent::recipe();
        }
    };

    $transport->responses[] = initializeResponse();
    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['content' => [['type' => 'text', 'text' => 'hi']], 'isError' => false],
    ]);

    (new Client($transport))->withCache()->callTool('say-hi');

    expect($transport->recipes)->toBe(0);
});

it('leaves the transport uncached until asked', function (): void {
    $transport = cacheableTransport();
    $client = new Client($transport);

    $client->tools();

    $sent = count($transport->sent);

    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('never serves one resource for another', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = resourceResponse('alpha');
    $transport->responses[] = resourceResponse('bravo');

    $client = (new Client($transport))->withCache();

    expect($client->readResource('file://a')->content())->toBe('alpha')
        ->and($client->readResource('file://b')->content())->toBe('bravo');
});

it('never caches an interim result', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = resourceResponse('alpha', resultType: 'input_required');
    $transport->responses[] = resourceResponse('bravo');

    $client = (new Client($transport))->withCache();

    expect($client->readResource('file://a')->content())->toBe('alpha')
        ->and($client->readResource('file://a')->content())->toBe('bravo');
});

it('never caches a page of a paginated list', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsPage('add', nextCursor: 'page-2');
    $transport->responses[] = toolsPage('subtract');
    $transport->responses[] = toolsPage('add', nextCursor: 'page-2');
    $transport->responses[] = toolsPage('subtract');

    $client = (new Client($transport))->withCache();

    expect($client->tools()->keys()->all())->toBe(['add', 'subtract']);

    $sent = count($transport->sent);

    expect($client->tools()->keys()->all())->toBe(['add', 'subtract'])
        ->and(count($transport->sent))->toBeGreaterThan($sent);
});

it('stops caching once the cache is turned back off', function (): void {
    $transport = cacheableTransport();
    $client = (new Client($transport))->withCache()->withoutCache();

    $client->tools();

    $sent = count($transport->sent);

    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add']]],
    ]);

    $client->tools();

    expect(count($transport->sent))->toBeGreaterThan($sent);
});

it('keeps caching through a serialize round-trip', function (): void {
    $client = Client::local('node', ['server.js'])->withCache(store: 'array', context: 'tenant-a');

    $restored = unserialize(serialize($client));

    expect($restored->__serialize()['cache'])
        ->toBeInstanceOf(ResponseCache::class)
        ->store->toBe('array')
        ->context->toBe('tenant-a');
});
