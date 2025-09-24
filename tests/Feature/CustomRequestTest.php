<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class MyCustomRequest extends Request
{
    public function myMethod(): string
    {
        return $this->string('message')->value();
    }
}

class LaptopShopServer extends Server
{
    protected array $prompts = [
        AskLaptop::class,
    ];

    protected array $tools = [
        BuyLaptop::class,
    ];

    protected array $resources = [
        LaptopGuidelines::class,
    ];
}

class AskLaptop extends Prompt
{
    public function handle(MyCustomRequest $request): string
    {
        return $request->myMethod();
    }
}

class BuyLaptop extends Tool
{
    public function handle(MyCustomRequest $request): string
    {
        return $request->myMethod();
    }
}

class LaptopGuidelines extends Resource
{
    public function handle(MyCustomRequest $request): string
    {
        return $request->myMethod();
    }
}

it('can use the custom request class on prompts', function (): void {
    $response = LaptopShopServer::prompt(AskLaptop::class, [
        'message' => 'Hello, Prompt!',
    ]);

    $response->assertSee('Hello, Prompt!');
});

it('can use the custom request class on tools', function (): void {
    $response = LaptopShopServer::tool(BuyLaptop::class, [
        'message' => 'Hello, Tool!',
    ]);

    $response->assertSee('Hello, Tool!');
});

it('can use the custom request class on resources', function (): void {
    $response = LaptopShopServer::resource(LaptopGuidelines::class, [
        'message' => 'Hello, Resource!',
    ]);

    $response->assertSee('Hello, Resource!');
});
