<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;

class GarageDiagnosticsServer extends Server
{
    protected array $tools = [
        EngineDiagnosticTool::class,
    ];
}

class EngineDiagnosticTool extends Tool
{
    public function __construct(private readonly DateTime $time) {}

    public static $shouldRegister = true;

    public function shouldRegister(Request $request): bool
    {
        return static::$shouldRegister;
    }

    protected string $name = 'garage/engine-diagnostics';

    public function handle(): string
    {
        return "Diagnostics completed in {$this->time->format('H:i:s')}.";
    }
}

it('may assert tools that require container dependencies are registered', function (): void {
    GarageDiagnosticsServer::tools()->assertRegistered([
        EngineDiagnosticTool::class,
    ]);
});

it('may fail to assert tools that require container dependencies are not registered', function (): void {
    EngineDiagnosticTool::$shouldRegister = false;
    GarageDiagnosticsServer::tools()->assertNotRegistered([
        EngineDiagnosticTool::class,
    ]);
})->throws(AssertionFailedError::class, 'The expected class ['.EngineDiagnosticTool::class.'] was not found in the response content.');
