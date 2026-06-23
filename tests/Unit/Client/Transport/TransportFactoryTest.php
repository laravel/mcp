<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Client\Transport\TransportFactory;
use Laravel\Mcp\Exceptions\ClientException;

it('rebuilds a stdio transport from its recipe', function (): void {
    $recipe = (new StdioTransport('node', ['server.js']))->recipe();
    $recipe['timeoutSeconds'] = 12.0;

    $transport = TransportFactory::fromRecipe($recipe);

    expect($transport)
        ->toBeInstanceOf(StdioTransport::class)
        ->and($transport->recipe())->toBe([
            'driver' => 'stdio',
            'command' => 'node',
            'args' => ['server.js'],
            'timeoutSeconds' => 12.0,
        ]);
});

it('rebuilds an http transport with its token and timeout from a recipe', function (): void {
    $source = new HttpTransport('https://mcp.test/mcp');
    $source->withToken('tok');
    $source->setTimeoutSeconds(7.5);

    $transport = TransportFactory::fromRecipe($source->recipe());

    expect($transport)
        ->toBeInstanceOf(HttpTransport::class)
        ->and($transport->recipe())->toBe([
            'driver' => 'http',
            'url' => 'https://mcp.test/mcp',
            'token' => 'tok',
            'headers' => [],
            'timeoutSeconds' => 7.5,
        ]);
});

it('rebuilds an http transport with its custom headers from a recipe', function (): void {
    $source = new HttpTransport('https://mcp.test/mcp');
    $source->withHeaders(['Authorization' => 'raw-api-key', 'X-Tenant-Id' => 'acme']);

    $transport = TransportFactory::fromRecipe($source->recipe());

    expect($transport)
        ->toBeInstanceOf(HttpTransport::class)
        ->and($transport->recipe())->toBe([
            'driver' => 'http',
            'url' => 'https://mcp.test/mcp',
            'token' => null,
            'headers' => ['Authorization' => 'raw-api-key', 'X-Tenant-Id' => 'acme'],
            'timeoutSeconds' => 30.0,
        ]);
});

it('throws for an unknown transport driver', function (): void {
    expect(fn (): Transport => TransportFactory::fromRecipe(['driver' => 'carrier-pigeon']))
        ->toThrow(ClientException::class, 'Unable to rebuild transport from an unknown recipe.');
});

it('throws when a stdio recipe is missing its command', function (): void {
    expect(fn (): Transport => TransportFactory::fromRecipe(['driver' => 'stdio', 'args' => []]))
        ->toThrow(ClientException::class, 'Invalid stdio transport recipe.');
});

it('throws when an http recipe is missing its url', function (): void {
    expect(fn (): Transport => TransportFactory::fromRecipe(['driver' => 'http']))
        ->toThrow(ClientException::class, 'Invalid http transport recipe.');
});
