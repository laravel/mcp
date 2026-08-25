<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Resource;
use PHPUnit\Framework\AssertionFailedError;

class ShopNotRegisteredTestResourceServer extends Server
{
    protected array $resources = [
        BuyNotRegisteredTestResource::class,
    ];
}

class BuyNotRegisteredTestResource extends Resource
{
    public static $shouldRegister = true;

    public function shouldRegister(Request $request): bool
    {
        return static::$shouldRegister;
    }

    public function handle(Request $request): string
    {
        return 'Purchase successful!';
    }
}

it('may assert that resource is not registered', function (): void {
    BuyNotRegisteredTestResource::$shouldRegister = false;

    $response = ShopNotRegisteredTestResourceServer::resource(BuyNotRegisteredTestResource::class);

    $response->assertNotRegistered();
});

it('may fail to assert that resource is not registered', function (): void {
    BuyNotRegisteredTestResource::$shouldRegister = true;

    $response = ShopNotRegisteredTestResourceServer::resource(BuyNotRegisteredTestResource::class);

    $response->assertNotRegistered();
})->throws(AssertionFailedError::class, 'The resource ['.BuyNotRegisteredTestResource::class.'] is registered.');
