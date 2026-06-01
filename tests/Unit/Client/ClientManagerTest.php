<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Client\RegisteredClient;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;
use Tests\Fixtures\Client\FakeTransport;
use Tests\Fixtures\Client\ThrowingTransport;

it('registers a named client and resolves it by name', function (): void {
    Mcp::registerClient('everything', fn (): Client => new Client(new FakeTransport));

    expect(Mcp::client('everything'))->toBeInstanceOf(RegisteredClient::class);
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
    expect(fn () => Mcp::client('missing'))
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

it('caches a named client tools list across resolutions', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    expect(Mcp::client('everything')->tools()->keys()->all())->toBe(['add']);
    expect(Mcp::client('everything')->tools()->keys()->all())->toBe(['add']);
    expect($transport->responses)->toBeEmpty();
    expect($transport->sent)->toHaveCount(3);
});

it('forwards subclass methods of the registered client and preserves fluent chaining', function (): void {
    $web = new WebClient(new HttpTransport('https://example.test/mcp'));

    Mcp::registerClient('remote', fn (): WebClient => $web);

    $client = Mcp::client('remote');

    expect($client->withToken('secret'))->toBe($client);
});

it('bypasses the cache when a tools limit is given', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = toolsResponse(3);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    expect(Mcp::client('everything')->tools(1)->keys()->all())->toBe(['add']);
    expect(Mcp::client('everything')->tools(1)->keys()->all())->toBe(['add']);
    expect($transport->responses)->toBeEmpty();
});

it('forwards callTool / ping / connected / initializeResult / withTimeout to the inner client', function (): void {
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

it('keeps a separate cache per client name', function (): void {
    $alpha = new FakeTransport;
    $alpha->responses[] = initializeResponse();
    $alpha->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['tools' => [['name' => 'alpha']]],
    ]);

    $beta = new FakeTransport;
    $beta->responses[] = initializeResponse();
    $beta->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['tools' => [['name' => 'beta']]],
    ]);

    Mcp::registerClient('alpha', fn (): Client => new Client($alpha));
    Mcp::registerClient('beta', fn (): Client => new Client($beta));

    expect(Mcp::client('alpha')->tools()->keys()->all())->toBe(['alpha']);
    expect(Mcp::client('beta')->tools()->keys()->all())->toBe(['beta']);
});

it('caches with an explicit ttl', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    Mcp::registerClient('everything', fn (): Client => new Client($transport), cacheTtl: 1800);

    Mcp::client('everything')->tools();
    Mcp::client('everything')->tools();

    expect($transport->responses)->toBeEmpty();
});

it('treats a zero ttl as no cache', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = toolsResponse(3);

    Mcp::registerClient('dev', fn (): Client => new Client($transport), cacheTtl: 0);

    Mcp::client('dev')->tools();
    Mcp::client('dev')->tools();

    expect($transport->responses)->toBeEmpty();
});

it('rehydrates cached tools against the live client', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['content' => [['type' => 'text', 'text' => 'three']], 'isError' => false],
    ]);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    Mcp::client('everything')->tools();
    $result = Mcp::client('everything')->tools()['add']->call(['a' => 1, 'b' => 2]);

    expect($result->text())->toBe('three');
});

it('flushes the cached tools list on demand', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = toolsResponse(3);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    Mcp::client('everything')->tools();
    Mcp::client('everything')->flushCache();
    Mcp::client('everything')->tools();

    expect($transport->responses)->toBeEmpty();
});

it('does not cache inline clients', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);
    $transport->responses[] = toolsResponse(3);

    $client = new Client($transport);
    $client->tools();
    $client->tools();

    expect($transport->responses)->toBeEmpty();
});

it('scopes the tools list cache per resolved scope value', function (): void {
    $alpha = new FakeTransport;
    $alpha->responses[] = initializeResponse();
    $alpha->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['tools' => [['name' => 'alpha-tool']]],
    ]);
    $alpha->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'beta-tool']]],
    ]);

    $scope = 1;

    Mcp::registerClient(
        'notion',
        fn (): Client => new Client($alpha),
        scope: function () use (&$scope): int {
            return $scope;
        },
    );

    expect(Mcp::client('notion')->tools()->keys()->all())->toBe(['alpha-tool']);

    $scope = 2;

    expect(Mcp::client('notion')->tools()->keys()->all())->toBe(['beta-tool']);
    expect($alpha->responses)->toBeEmpty();
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

it('refetches when the cached payload no longer validates', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    app(Repository::class)
        ->put('mcp-list:everything:tools', [['not-a-valid' => 'tool-payload']], 3600);

    expect(Mcp::client('everything')->tools()->keys()->all())->toBe(['add']);
    expect($transport->responses)->toBeEmpty();
});

it('refetches when the cached payload contains a non-array entry', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    Mcp::registerClient('everything', fn (): Client => new Client($transport));

    app(Repository::class)
        ->put('mcp-list:everything:tools', ['not-an-array'], 3600);

    expect(Mcp::client('everything')->tools()->keys()->all())->toBe(['add']);
    expect($transport->responses)->toBeEmpty();
});

it('does not retry on a live fetch failure', function (): void {
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

it('uses the auth identifier when a scope closure returns an Authenticatable', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = toolsResponse(2);

    $user = new class implements Authenticatable
    {
        public function getAuthIdentifier(): int
        {
            return 42;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    Mcp::registerClient(
        'notion',
        fn (): Client => new Client($transport),
        scope: fn (): Authenticatable => $user,
    );

    Mcp::client('notion')->tools();

    expect(app(Repository::class)->get('mcp-list:notion:tools:scope:42'))->toBeArray();
});

it('throws when the scope closure returns an unsupported type', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    Mcp::registerClient(
        'notion',
        fn (): Client => new Client($transport),
        scope: fn (): array => ['oops'],
    );

    expect(fn () => Mcp::client('notion')->tools())
        ->toThrow(ClientException::class, 'MCP cache scope closure must return');
});
