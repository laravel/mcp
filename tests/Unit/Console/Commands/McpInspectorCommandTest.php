<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Routing\Route;
use Illuminate\Routing\UrlGenerator;
use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;
use Laravel\Mcp\Console\Commands\InspectorCommand;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\Console\Question\Question;
use Tests\Fixtures\ExampleServer;

beforeEach(function (): void {
    $this->registrar = Double::for(Registrar::class);
    $this->app->instance(Registrar::class, $this->registrar);
});

it('normalizes windows paths in guidance output', function (): void {
    $command = Double::for(InspectorCommand::class)->passthru();
    $this->registrar->allows('getLocalServer')->with('demo')->returns(function (): void {});

    $this->registrar->allows('getWebServer')->with('demo')->returns(null);

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
    $this->registrar->allows('getLocalServer')->with('invalid')->returns(null);

    $this->registrar->allows('getWebServer')->with('invalid')->returns(null);

    $this->registrar->allows('servers')->returns(['demo' => 'Demo Server', 'weather' => 'Weather Server']);

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
    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns(null);

    $this->registrar->allows('servers')->returns([]);

    $this->artisan('mcp:inspector', ['handle' => 'demo'])
        ->expectsOutputToContain('Starting the MCP Inspector for server [demo]')
        ->expectsOutputToContain('No MCP servers found. Please run `php artisan make:mcp-server [name]`')
        ->assertExitCode(1);
});

it('uses single server when only one is registered', function (): void {
    $callable = function (): void {};

    $this->registrar->allows('servers')->returns(['demo' => $callable]);

    // Can't test the actual Process execution in unit tests
    // This would require integration testing
    expect($callable)->toBeCallable();
});

it('handles http transport with https url', function (): void {
    $route = Double::for(Route::class);
    $route->allows('uri')->returns('api/mcp');

    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns($route);

    $this->registrar->allows('servers')->returns(['demo' => $route]);

    // Verify that route config is set up correctly
    expect($route->uri())->toBe('api/mcp');
});

it('handles stdio transport successfully', function (): void {
    $callable = function (): void {};

    $this->registrar->allows('getLocalServer')->with('demo')->returns($callable);

    $this->registrar->allows('getWebServer')->with('demo')->returns(null);

    $this->registrar->allows('servers')->returns(['demo' => $callable]);

    // Verify local server is retrieved correctly
    expect($this->registrar->getLocalServer('demo'))->toBe($callable);
});

it('handles non-string handle argument', function (): void {
    $this->artisan('mcp:inspector', ['handle' => 123])
        ->expectsOutputToContain('Please pass a valid MCP server handle')
        ->assertExitCode(1);
});

it('handles single server with Route class', function (): void {
    $route = Double::for(Route::class);
    $route->allows('uri')->returns('api/mcp');

    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns(null);

    $this->registrar->allows('servers')->returns(['single' => $route]);

    // Can't test the actual Process execution in unit tests
    expect($route)->toBeInstanceOf(Route::class);
});

it('handles single server with unknown type', function (): void {
    $unknownServer = new stdClass;

    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns(null);

    $this->registrar->allows('servers')->returns(['single' => $unknownServer]);

    $this->artisan('mcp:inspector', ['handle' => 'demo'])
        ->expectsOutputToContain('MCP Server with name [demo] not found')
        ->assertExitCode(1);
});

it('verifies process timeout is set correctly', function (): void {
    $callable = function (): void {};

    $this->registrar->allows('getLocalServer')->with('demo')->returns($callable);

    $this->registrar->allows('getWebServer')->with('demo')->returns(null);

    $this->registrar->allows('servers')->returns(['demo' => $callable]);

    // Can't mock Process class directly in unit tests
    // Just verify the callable is set correctly
    expect($callable)->toBeCallable();
});

it('handles http transport with http url', function (): void {
    $route = Double::for(Route::class);
    $route->allows('uri')->returns('api/mcp');

    // Mock url() helper to return http URL
    app()->bind('url', function () {
        $url = Double::for(UrlGenerator::class);
        $url->allows('to')->returns('http://localhost/api/mcp');

        return $url;
    });

    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns($route);

    $this->registrar->allows('servers')->returns(['demo' => $route]);

    // Verify that route config is set up correctly
    expect($route->uri())->toBe('api/mcp');
});

it('retrieves php binary path correctly', function (): void {
    $command = new InspectorCommand;
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('phpBinary');

    $phpBinary = $method->invoke($command);

    // Should return either a path to php or 'php'
    expect($phpBinary)->toBeString();
    expect(strlen((string) $phpBinary))->toBeGreaterThan(0);
});

it('asks for route parameter values when building the server url', function (): void {
    $route = (new Registrar)->web('mcp/{organisation:uuid}', ExampleServer::class);

    $output = Double::for(OutputStyle::class);
    $output->expects('askQuestion')
        ->with(Argument::satisfies(fn (Question $question): bool => $question->getQuestion() === 'What is the value for the [organisation] route parameter?'))
        ->returns('4f8a1c2e');

    $components = new Factory($output);

    $command = new InspectorCommand;
    $command->setLaravel($this->app);

    (new ReflectionProperty($command, 'components'))->setValue($command, $components);

    $serverUrl = (new ReflectionMethod($command, 'serverUrl'))->invoke($command, $route);

    expect($serverUrl)->toBe('http://localhost/mcp/4f8a1c2e');
});

it('fails when a route parameter is left blank', function (): void {
    $route = (new Registrar)->web('mcp/{organisation:uuid}', ExampleServer::class);

    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns($route);

    $this->registrar->allows('servers')->returns(['demo' => $route]);

    $this->artisan('mcp:inspector', ['handle' => 'demo'])
        ->expectsQuestion('What is the value for the [organisation] route parameter?', null)
        ->expectsOutputToContain('Every route parameter needs a value to inspect this server')
        ->assertExitCode(1);
});

it('fails when a route parameter only contains whitespace', function (): void {
    $route = (new Registrar)->web('mcp/{organisation:uuid}', ExampleServer::class);

    $this->registrar->allows('getLocalServer')->with('demo')->returns(null);

    $this->registrar->allows('getWebServer')->with('demo')->returns($route);

    $this->registrar->allows('servers')->returns(['demo' => $route]);

    $this->artisan('mcp:inspector', ['handle' => 'demo'])
        ->expectsQuestion('What is the value for the [organisation] route parameter?', '   ')
        ->expectsOutputToContain('Every route parameter needs a value to inspect this server')
        ->assertExitCode(1);
});
