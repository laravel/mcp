<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;

class Shop extends Server
{
    protected array $tools = [
        BuyTool::class,
    ];
}

class BuyTool extends Tool
{
    public function handle(Request $request): string
    {
        $request->validate([
            'id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:5',
        ]);

        return 'Purchase successful!';
    }
}

it('may assert validation passes', function (): void {
    $response = Shop::tool(BuyTool::class, ['id' => 1, 'quantity' => 3]);

    $response->assertHasNoErrors();
});

it('may assert that things are ok', function (): void {
    $response = Shop::tool(BuyTool::class, ['id' => 1, 'quantity' => 3]);

    $response->assertOk();
});

it('may fail to assert that things are ok', function (): void {
    $response = Shop::tool(BuyTool::class);

    $response->assertOk();
})->throws(AssertionFailedError::class);

it('may assert validation fails', function (): void {
    $response = Shop::tool(BuyTool::class);

    $response->assertHasErrors();
});

it('may fail to assert validation fails', function (): void {
    $response = Shop::tool(BuyTool::class, ['id' => 1]);

    $response->assertHasErrors([
        'The id field is required.',
    ]);
})->throws(AssertionFailedError::class);

it('may assert specific validation errors', function (): void {
    $response = Shop::tool(BuyTool::class, ['id' => 1]);

    $response->assertHasErrors([
        'The quantity field is required.',
    ]);
});
