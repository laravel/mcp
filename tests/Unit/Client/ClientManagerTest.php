<?php

use Laravel\Mcp\Client\Auth\AuthorizationCodeProvider;
use Laravel\Mcp\Client\Auth\ClientCredentialsProvider;
use Laravel\Mcp\Client\Client;
use Laravel\Mcp\Client\ClientManager;
use Laravel\Mcp\Client\Transport\HttpClientTransport;
use Laravel\Mcp\Client\Transport\StdioClientTransport;
use Tests\Fixtures\FakeClientTransport;

it('creates stdio transport from config', function (): void {
    $manager = new ClientManager;

    $transport = $manager->createTransport([
        'transport' => 'stdio',
        'command' => 'php',
        'args' => ['artisan', 'mcp:start', 'example'],
        'timeout' => 15,
    ]);

    expect($transport)->toBeInstanceOf(StdioClientTransport::class);
});

it('creates http transport from config', function (): void {
    $manager = new ClientManager;

    $transport = $manager->createTransport([
        'transport' => 'http',
        'url' => 'https://example.com/mcp',
        'headers' => ['Authorization' => 'Bearer token'],
        'timeout' => 60,
    ]);

    expect($transport)->toBeInstanceOf(HttpClientTransport::class);
});

it('throws for unsupported transport', function (): void {
    $manager = new ClientManager;

    $manager->createTransport([
        'transport' => 'websocket',
    ]);
})->throws(InvalidArgumentException::class, 'Unsupported MCP transport [websocket].');

it('throws for unconfigured server', function (): void {
    $manager = new ClientManager;

    $manager->client('nonexistent');
})->throws(InvalidArgumentException::class, 'MCP server [nonexistent] is not configured.');

it('defaults to stdio transport', function (): void {
    $manager = new ClientManager;

    $transport = $manager->createTransport([
        'command' => 'php',
    ]);

    expect($transport)->toBeInstanceOf(StdioClientTransport::class);
});

it('purges all clients', function (): void {
    $manager = new ClientManager;

    $initResponse = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'serverInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ]);

    $transport1 = new FakeClientTransport([$initResponse]);
    $client1 = new Client($transport1, 'server-a');
    $client1->connect();

    $transport2 = new FakeClientTransport([$initResponse]);
    $client2 = new Client($transport2, 'server-b');
    $client2->connect();

    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('clients');
    $property->setValue($manager, ['server-a' => $client1, 'server-b' => $client2]);

    $manager->purge();

    expect($client1->isConnected())->toBeFalse()
        ->and($client2->isConnected())->toBeFalse();
});

it('purges a specific client', function (): void {
    $manager = new ClientManager;

    $initResponse = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'serverInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ]);

    $transport = new FakeClientTransport([$initResponse]);
    $client = new Client($transport, 'server-a');
    $client->connect();

    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('clients');
    $property->setValue($manager, ['server-a' => $client]);

    $manager->purge('server-a');

    expect($client->isConnected())->toBeFalse();
});

it('purge ignores nonexistent named client', function (): void {
    $manager = new ClientManager;

    $manager->purge('nonexistent');

    expect(true)->toBeTrue();
});

it('creates client credentials auth provider from config', function (): void {
    $manager = new ClientManager;

    $provider = $manager->createAuthProvider([
        'type' => 'client_credentials',
        'client_id' => 'my-client-id',
        'client_secret' => 'my-client-secret',
        'scope' => 'custom:scope',
    ], 'test-server');

    expect($provider)->toBeInstanceOf(ClientCredentialsProvider::class);
});

it('creates authorization code auth provider from config', function (): void {
    $manager = new ClientManager;

    $provider = $manager->createAuthProvider([
        'type' => 'authorization_code',
        'client_id' => 'my-client-id',
        'redirect_uri' => 'https://app.example.com/callback',
        'scope' => 'mcp:use',
    ], 'test-server');

    expect($provider)->toBeInstanceOf(AuthorizationCodeProvider::class);
});

it('throws for unsupported auth type', function (): void {
    $manager = new ClientManager;

    $manager->createAuthProvider([
        'type' => 'private_key_jwt',
    ], 'test-server');
})->throws(InvalidArgumentException::class, 'Unsupported MCP auth type [private_key_jwt].');

it('creates http transport with auth provider', function (): void {
    $manager = new ClientManager;

    $transport = $manager->createTransport([
        'transport' => 'http',
        'url' => 'https://example.com/mcp',
        'auth' => [
            'type' => 'client_credentials',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
        ],
    ], 'test-server');

    expect($transport)->toBeInstanceOf(HttpClientTransport::class);
});

it('returns cached client on second call', function (): void {
    $manager = new ClientManager;

    $initResponse = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'serverInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ],
    ]);

    $transport = new FakeClientTransport([$initResponse]);
    $client = new Client($transport, 'test-server');
    $client->connect();

    $reflection = new ReflectionClass($manager);
    $property = $reflection->getProperty('clients');
    $property->setValue($manager, ['test-server' => $client]);

    $result = $manager->client('test-server');

    expect($result)->toBe($client);
});
