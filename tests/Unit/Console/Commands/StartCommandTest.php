<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Registrar;
use Symfony\Component\Console\Exception\RuntimeException;

beforeEach(function (): void {
    $this->registrar = Mockery::mock(Registrar::class);
    $this->app->instance(Registrar::class, $this->registrar);
});

it('starts the local server when the handle exists', function (): void {
    $ran = false;

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->once()
        ->with('demo')
        ->andReturn(function () use (&$ran): void {
            $ran = true;
        });

    $this->artisan('mcp:start', ['handle' => 'demo'])
        ->assertExitCode(0);

    expect($ran)->toBeTrue();
});

it('fails with an error when the handle is unknown', function (): void {
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->once()
        ->with('missing')
        ->andReturn(null);

    $this->artisan('mcp:start', ['handle' => 'missing'])
        ->expectsOutputToContain('MCP Server with name [missing] not found. Did you register it using [Mcp::local()]?')
        ->assertExitCode(1);
});

it('always returns success even if the server returns an int', function (): void {
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->once()
        ->with('code')
        ->andReturn(fn (): int => 2);

    $this->artisan('mcp:start', ['handle' => 'code'])
        ->assertExitCode(0);
});

it('requires the handle argument', function (): void {
    try {
        $this->artisan('mcp:start')
            ->expectsOutputToContain('handle')
            ->assertExitCode(1);
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toContain('handle');
    }
});
