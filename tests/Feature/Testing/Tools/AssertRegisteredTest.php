<?php

declare(strict_types=1);

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;

class GarageListToolsServer extends Server
{
    public int $defaultPaginationLength = 1;

    protected array $tools = [
        OilChangeTool::class,
        TireRotationTool::class,
        EngineDiagnosticTool::class,
    ];
}

class DiagnosticsRepository {}

class OilChangeTool extends Tool
{
    public static $shouldRegister = true;

    protected string $name = 'garage/oil-change';

    public function shouldRegister(): bool
    {
        return static::$shouldRegister;
    }

    public function handle(): string
    {
        return 'Oil changed.';
    }
}

class TireRotationTool extends Tool
{
    protected string $name = 'garage/tire-rotation';

    public function handle(): string
    {
        return 'Tires rotated.';
    }
}

class EngineDiagnosticTool extends Tool
{
    protected string $name = 'garage/engine-diagnostics';

    public function __construct(protected DiagnosticsRepository $diagnostics) {}

    public function handle(): string
    {
        return 'Diagnostics complete.';
    }
}

class BrakeInspectionTool extends Tool
{
    protected string $name = 'garage/brake-inspection';

    public function __construct(protected DiagnosticsRepository $diagnostics) {}

    public function handle(): string
    {
        return 'Brakes inspected.';
    }
}

it('may assert that tools are registered on a server', function (): void {
    OilChangeTool::$shouldRegister = true;

    GarageListToolsServer::tools()->assertRegistered([
        OilChangeTool::class,
        TireRotationTool::class,
    ]);
});

it('may fail to assert that tools are registered on a server', function (): void {
    OilChangeTool::$shouldRegister = false;

    GarageListToolsServer::tools()->assertRegistered([
        TireRotationTool::class,
        OilChangeTool::class,
    ]);
})->throws(AssertionFailedError::class, 'The expected class ['.OilChangeTool::class.'] was not found in the response content.');

it('may assert that tools are not registered on a server', function (): void {
    OilChangeTool::$shouldRegister = false;

    GarageListToolsServer::tools()->assertRegistered([
        TireRotationTool::class,
    ])->assertNotRegistered([
        OilChangeTool::class,
    ]);
});

it('may fail to assert that tools are not registered on a server', function (): void {
    OilChangeTool::$shouldRegister = true;

    GarageListToolsServer::tools()->assertRegistered([
        TireRotationTool::class,
    ])->assertNotRegistered([
        OilChangeTool::class,
    ]);
})->throws(AssertionFailedError::class, 'The expected class ['.OilChangeTool::class.'] was found in the response content.');

it('resolves tools with constructor dependencies when asserting they are registered', function (): void {
    GarageListToolsServer::tools()->assertRegistered(EngineDiagnosticTool::class);
});

it('resolves tools with constructor dependencies when asserting they are not registered', function (): void {
    GarageListToolsServer::tools()->assertNotRegistered(BrakeInspectionTool::class);
});
