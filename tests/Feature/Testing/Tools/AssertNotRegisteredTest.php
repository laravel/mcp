<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;

class ShopNotRegisteredTestServer extends Server
{
    protected array $tools = [
        BuyNotRegisteredTestTool::class,
    ];
}

class BuyNotRegisteredTestTool extends Tool
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

it('may assert that tool is not registered', function (): void {
    BuyNotRegisteredTestTool::$shouldRegister = false;

    $response = ShopNotRegisteredTestServer::tool(BuyNotRegisteredTestTool::class);

    $response->assertNotRegistered();
});

it('may fail to assert that tool is not registered', function (): void {
    BuyNotRegisteredTestTool::$shouldRegister = true;

    $response = ShopNotRegisteredTestServer::tool(BuyNotRegisteredTestTool::class);

    $response->assertNotRegistered();
})->throws(AssertionFailedError::class, 'The tool ['.BuyNotRegisteredTestTool::class.'] is registered.');
