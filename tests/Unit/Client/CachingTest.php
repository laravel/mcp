<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Tests\Fixtures\Client\FakeTransport;

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

    expect($second->sent)->not->toBeEmpty();
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
