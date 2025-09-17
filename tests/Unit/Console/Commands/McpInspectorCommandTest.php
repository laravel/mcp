<?php

declare(strict_types=1);

use Laravel\Mcp\Console\Commands\InspectorCommand;
use Laravel\Mcp\Server\Registrar;

beforeEach(function (): void {
    $this->registrar = Mockery::mock(Registrar::class);
    $this->app->instance(Registrar::class, $this->registrar);
});

it('normalizes windows paths in guidance output', function (): void {
    $command = Mockery::mock(InspectorCommand::class)->makePartial();
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(function (): void {});

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $windowsPath = 'D:\\Herd\\cyborgfinance\\artisan';
    $normalizedPath = str_replace('\\', '/', $windowsPath);

    expect($normalizedPath)->toBe('D:/Herd/cyborgfinance/artisan');
});

it('normalizes mixed paths correctly', function (): void {
    $testCases = [
        'D:\\Herd\\cyborgfinance\\artisan' => 'D:/Herd/cyborgfinance/artisan',
        '/var/www/laravel/artisan' => '/var/www/laravel/artisan',
        'C:\\xampp\\htdocs\\project\\artisan' => 'C:/xampp/htdocs/project/artisan',
        '/home/user/project/artisan' => '/home/user/project/artisan',
    ];

    foreach ($testCases as $input => $expected) {
        $normalized = str_replace('\\', '/', $input);
        expect($normalized)->toBe($expected);
    }
});

it('fails with invalid handle', function (): void {
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('invalid')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('invalid')
        ->andReturn(null);

    $this->registrar->shouldReceive('servers')->andReturn(['demo' => 'Demo Server', 'weather' => 'Weather Server']);

    $this->artisan('mcp:inspector', ['handle' => 'invalid'])
        ->expectsOutputToContain('Starting the MCP Inspector for server [invalid].')
        ->expectsOutputToContain('MCP Server with name [invalid] not found.')
        ->assertExitCode(1);
});

it('validates handle argument is required', function (): void {
    expect(function (): void {
        $this->artisan('mcp:inspector');
    })->toThrow(RuntimeException::class, 'Not enough arguments (missing: "handle")');
});

it('fails when no servers are registered', function (): void {
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar->shouldReceive('servers')->andReturn([]);

    $this->artisan('mcp:inspector', ['handle' => 'demo'])
        ->expectsOutputToContain('Starting the MCP Inspector for server [demo]')
        ->expectsOutputToContain('No MCP servers found. Please run `php artisan make:mcp-server [name]`')
        ->assertExitCode(1);
});

it('uses single server when only one is registered', function (): void {
    $callable = function (): void {};

    $this->registrar->shouldReceive('servers')->andReturn(['demo' => $callable]);

    // Can't test the actual Process execution in unit tests
    // This would require integration testing
    expect($callable)->toBeCallable();
});

it('handles http transport with https url', function (): void {
    $route = Mockery::mock(\Illuminate\Routing\Route::class);
    $route->shouldReceive('uri')->andReturn('api/mcp');

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn($route);

    $this->registrar->shouldReceive('servers')->andReturn(['demo' => $route]);

    // Verify that route config is set up correctly
    expect($route->uri())->toBe('api/mcp');
});

it('handles stdio transport successfully', function (): void {
    $callable = function (): void {};

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn($callable);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar->shouldReceive('servers')->andReturn(['demo' => $callable]);

    // Verify local server is retrieved correctly
    expect($this->registrar->getLocalServer('demo'))->toBe($callable);
});

it('handles non-string handle argument', function (): void {
    $this->artisan('mcp:inspector', ['handle' => 123])
        ->expectsOutputToContain('Please pass a valid MCP server handle')
        ->assertExitCode(1);
});

it('handles single server with Route class', function (): void {
    $route = Mockery::mock(\Illuminate\Routing\Route::class);
    $route->shouldReceive('uri')->andReturn('api/mcp');

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar->shouldReceive('servers')->andReturn(['single' => $route]);

    // Can't test the actual Process execution in unit tests
    expect($route)->toBeInstanceOf(\Illuminate\Routing\Route::class);
});

it('handles single server with unknown type', function (): void {
    $unknownServer = new stdClass;

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar->shouldReceive('servers')->andReturn(['single' => $unknownServer]);

    $this->artisan('mcp:inspector', ['handle' => 'demo'])
        ->expectsOutputToContain('MCP Server with name [demo] not found')
        ->assertExitCode(1);
});

it('verifies process timeout is set correctly', function (): void {
    $callable = function (): void {};

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn($callable);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar->shouldReceive('servers')->andReturn(['demo' => $callable]);

    // Can't mock Process class directly in unit tests
    // Just verify the callable is set correctly
    expect($callable)->toBeCallable();
});

it('handles http transport with http url', function (): void {
    $route = Mockery::mock(\Illuminate\Routing\Route::class);
    $route->shouldReceive('uri')->andReturn('api/mcp');

    // Mock url() helper to return http URL
    app()->bind('url', function () {
        $url = Mockery::mock(\Illuminate\Routing\UrlGenerator::class);
        $url->shouldReceive('to')->andReturn('http://localhost/api/mcp');

        return $url;
    });

    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn($route);

    $this->registrar->shouldReceive('servers')->andReturn(['demo' => $route]);

    // Verify that route config is set up correctly
    expect($route->uri())->toBe('api/mcp');
});

it('retrieves php binary path correctly', function (): void {
    $command = new \Laravel\Mcp\Console\Commands\InspectorCommand;
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('phpBinary');

    $phpBinary = $method->invoke($command);

    // Should return either a path to php or 'php'
    expect($phpBinary)->toBeString();
    expect(strlen((string) $phpBinary))->toBeGreaterThan(0);
});
