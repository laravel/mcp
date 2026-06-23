<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;
use Tests\Fixtures\Client\FakeTransport;
use Tests\Fixtures\Client\ThrowingTransport;

function toolsResponse(int $id): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'tools' => [['name' => 'add', 'description' => 'Adds two numbers']],
        ],
    ]);
}

it('registers a named client and resolves it by name', function (): void {
    Mcp::registerClient('everything', fn (): Client => new Client(new FakeTransport));

    expect(Mcp::client('everything'))->toBeInstanceOf(Client::class);
});

it('resolves and memoizes a registered client per request', function (): void {
    $resolved = 0;

    Mcp::registerClient('everything', function () use (&$resolved): Client {
        $resolved++;

        return new Client(new FakeTransport);
    });

    $first = Mcp::client('everything');
    $second = Mcp::client('everything');

    expect($first)->toBe($second);
    expect($resolved)->toBe(1);
});

it('throws when resolving an unregistered client', function (): void {
    expect(fn (): Client => Mcp::client('missing'))
        ->toThrow(ClientException::class, 'MCP client [missing] has not been registered.');
});

it('resolves a fresh instance when the same name is re-registered', function (): void {
    Mcp::registerClient('everything', fn (): Client => new Client(new FakeTransport));
    $first = Mcp::client('everything');

    Mcp::registerClient('everything', fn (): Client => new Client(new FakeTransport));
    $second = Mcp::client('everything');

    expect($second)->not->toBe($first);
});

it('disconnects a resolved client when its name is re-registered', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    Mcp::registerClient('everything', fn (): Client => new Client($transport));
    Mcp::client('everything')->connect();

    expect($transport->connected)->toBeTrue();

    Mcp::registerClient('everything', fn (): Client => new Client(new FakeTransport));

    expect($transport->connected)->toBeFalse();
});

it('disconnects every resolved client and re-resolves afterwards', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $resolved = 0;
    Mcp::registerClient('everything', function () use (&$resolved, $transport): Client {
        $resolved++;

        return new Client($transport);
    });

    $first = Mcp::client('everything');
    $first->connect();

    expect($transport->connected)->toBeTrue();

    app(ClientManager::class)->disconnectAll();

    expect($transport->connected)->toBeFalse();

    $second = Mcp::client('everything');

    expect($resolved)->toBe(2);
    expect($second)->not->toBe($first);
});

it('keeps disconnecting other clients when one throws during teardown', function (): void {
    $healthy = new FakeTransport;
    $healthy->responses[] = initializeResponse();

    Mcp::registerClient('broken', fn (): Client => new Client(new ThrowingTransport));
    Mcp::registerClient('healthy', fn (): Client => new Client($healthy));

    Mcp::client('broken');
    Mcp::client('healthy')->connect();

    expect($healthy->connected)->toBeTrue();

    app(ClientManager::class)->disconnectAll();

    expect($healthy->connected)->toBeFalse();
});

it('fetches a fresh tools list on every resolution', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = toolsResponse(3);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    expect(Mcp::client('everything')->tools()->keys()->all())->toBe(['add']);
    expect(Mcp::client('everything')->tools()->keys()->all())->toBe(['add']);
    expect($transport->responses)->toBeEmpty();
});

it('returns the given default from a named client when authorization is required', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 401),
    ]);

    Mcp::registerClient('nightwatch', fn (): Client => new Client(new HttpTransport('https://mcp.test/mcp')));

    $tools = Mcp::client('nightwatch')->tools(default: []);

    expect($tools)->toBeInstanceOf(Collection::class)->toBeEmpty();
});

it('rethrows the authorization exception from a named client when no default is given', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 401),
    ]);

    Mcp::registerClient('nightwatch', fn (): Client => new Client(new HttpTransport('https://mcp.test/mcp')));

    expect(fn () => Mcp::client('nightwatch')->tools())
        ->toThrow(AuthorizationRequiredException::class);
});

it('resolves a subclassed client and preserves fluent chaining', function (): void {
    $web = new WebClient(new HttpTransport('https://example.test/mcp'));

    Mcp::registerClient('remote', fn (): WebClient => $web);

    $client = Mcp::client('remote');

    expect($client)->toBe($web);
    expect($client->withToken('secret'))->toBe($client);
});

it('applies a tools limit on the resolved client', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    expect(Mcp::client('everything')->tools(1)->keys()->all())->toBe(['add']);
    expect($transport->responses)->toBeEmpty();
});

it('exposes callTool / ping / connected / initializeResult / withTimeout on the resolved client', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = pingResponse(2);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['content' => [['type' => 'text', 'text' => 'hi']], 'isError' => false],
    ]);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    $client = Mcp::client('everything')->withTimeout(0.5);

    expect($client->connected())->toBeFalse();

    $client->ping();

    expect($client->connected())->toBeTrue();
    expect($client->initializeResult()?->serverInfo->name)->toBe('Test Server');
    expect($transport->timeoutSeconds)->toBe(0.5);
    expect($client->callTool('say-hi')->text())->toBe('hi');
});

it('returns a tools list the application can cache and restore', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    $tools = Mcp::client('everything')->tools();

    $restored = unserialize(serialize($tools));

    expect($restored->keys()->all())->toBe(['add']);
    expect($restored['add']->description)->toBe('Adds two numbers');
});

it('serializes a named client as its name only and re-resolves it when a restored tool is called', function (): void {
    $resolutions = 0;

    Mcp::registerClient('everything', function () use (&$resolutions): Client {
        $resolutions++;

        $transport = new FakeTransport;
        $transport->responses[] = initializeResponse();
        $transport->responses[] = $resolutions === 1
            ? toolsResponse(2)
            : json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => ['content' => [['type' => 'text', 'text' => 'added']], 'isError' => false],
            ]);

        return new Client($transport);
    });

    $payload = serialize(Mcp::client('everything')->tools());

    expect($payload)->toContain('everything')->not->toContain('secret');

    app(ClientManager::class)->disconnectAll();

    $restored = unserialize($payload);

    expect($restored['add']->call()->text())->toBe('added')
        ->and($resolutions)->toBe(2);
});

it('gives a restored named client its own transport instead of aliasing the cached one', function (): void {
    Mcp::registerClient('everything', fn (): Client => new Client(new FakeTransport));

    $live = Mcp::client('everything');

    $restored = unserialize(serialize($live));

    $transport = new ReflectionProperty(Client::class, 'transport');

    expect($restored)->not->toBe($live)
        ->and($transport->getValue($restored))->not->toBe($transport->getValue($live));
});

it('does not cache tools on the resolved client', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = toolsResponse(3);

    $client = new Client($transport);
    $client->tools();
    $client->tools();

    expect($transport->responses)->toBeEmpty();
});

it('still installs a fresh factory when an old client fails to disconnect', function (): void {
    $broken = new ThrowingTransport;
    $broken->responses[] = initializeResponse();

    Mcp::registerClient('everything', fn (): Client => new Client($broken));
    $first = Mcp::client('everything');
    $first->connect();

    $fresh = new FakeTransport;
    $fresh->responses[] = initializeResponse();
    Mcp::registerClient('everything', fn (): Client => new Client($fresh));

    $second = Mcp::client('everything');
    $second->connect();

    expect($second)->not->toBe($first);
    expect($fresh->connected)->toBeTrue();
});

it('propagates a live fetch failure', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['tools' => 'not-an-array'],
    ]);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    expect(fn () => Mcp::client('everything')->tools())
        ->toThrow(ClientException::class, 'Invalid tools/list response from server.');

    expect($transport->responses)->toBeEmpty();
});
