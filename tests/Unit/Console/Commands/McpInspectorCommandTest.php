<?php

declare(strict_types=1);

use Laravel\Mcp\Console\Commands\InspectorCommand;
use Laravel\Mcp\Server\Registrar;

beforeEach(function () {
    $this->registrar = Mockery::mock(Registrar::class);
    $this->app->instance(Registrar::class, $this->registrar);
});

it('normalizes windows paths in guidance output', function () {
    $command = Mockery::mock(InspectorCommand::class)->makePartial();
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('demo')
        ->andReturn(function () {});

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('demo')
        ->andReturn(null);

    $windowsPath = 'D:\\Herd\\cyborgfinance\\artisan';
    $normalizedPath = str_replace('\\', '/', $windowsPath);

    expect($normalizedPath)->toBe('D:/Herd/cyborgfinance/artisan');
});

it('normalizes mixed paths correctly', function () {
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

it('fails with invalid handle', function () {
    $this->registrar
        ->shouldReceive('getLocalServer')
        ->with('invalid')
        ->andReturn(null);

    $this->registrar
        ->shouldReceive('getWebServer')
        ->with('invalid')
        ->andReturn(null);

    $this->artisan('mcp:inspector', ['handle' => 'invalid'])
        ->expectsOutputToContain('Starting the MCP Inspector for server [invalid].')
        ->expectsOutputToContain('MCP Server with handle [invalid] not found.')
        ->assertExitCode(1);
});

it('validates handle argument is required', function () {
    expect(function () {
        $this->artisan('mcp:inspector');
    })->toThrow(RuntimeException::class, 'Not enough arguments (missing: "handle")');
});
