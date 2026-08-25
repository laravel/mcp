<?php

declare(strict_types=1);

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Resource;
use PHPUnit\Framework\AssertionFailedError;

class GarageListResourcesServer extends Server
{
    public int $defaultPaginationLength = 1;

    protected array $resources = [
        OilChangeResource::class,
        TireRotationResource::class,
    ];
}

class OilChangeResource extends Resource
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

class TireRotationResource extends Resource
{
    protected string $name = 'garage/tire-rotation';

    public function handle(): string
    {
        return 'Tires rotated.';
    }
}

it('may assert that resources are registered on a server', function (): void {
    OilChangeResource::$shouldRegister = true;

    GarageListResourcesServer::resources()->assertRegistered([
        OilChangeResource::class,
        TireRotationResource::class,
    ]);
});

it('may fail to assert that resources are registered on a server', function (): void {
    OilChangeResource::$shouldRegister = false;

    GarageListResourcesServer::resources()->assertRegistered([
        TireRotationResource::class,
        OilChangeResource::class,
    ]);
})->throws(AssertionFailedError::class, 'The expected class ['.OilChangeResource::class.'] was not found in the response content.');

it('may assert that resources are not registered on a server', function (): void {
    OilChangeResource::$shouldRegister = false;

    GarageListResourcesServer::resources()->assertRegistered([
        TireRotationResource::class,
    ])->assertNotRegistered([
        OilChangeResource::class,
    ]);
});

it('may fail to assert that resources are not registered on a server', function (): void {
    OilChangeResource::$shouldRegister = true;

    GarageListResourcesServer::resources()->assertRegistered([
        TireRotationResource::class,
    ])->assertNotRegistered([
        OilChangeResource::class,
    ]);

    $this->fail('Expected an AssertionFailedError to be thrown, but it was not.');
})->throws(AssertionFailedError::class, 'The expected class ['.OilChangeResource::class.'] was found in the response content.');
