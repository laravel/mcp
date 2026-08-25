<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use PHPUnit\Framework\AssertionFailedError;

class ShopNotRegisteredTestPromptServer extends Server
{
    protected array $prompts = [
        BuyNotRegisteredTestPrompt::class,
    ];
}

class BuyNotRegisteredTestPrompt extends Prompt
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

it('may assert that prompt is not registered', function (): void {
    BuyNotRegisteredTestPrompt::$shouldRegister = false;

    $response = ShopNotRegisteredTestPromptServer::prompt(BuyNotRegisteredTestPrompt::class);

    $response->assertNotRegistered();
});

it('may fail to assert that prompt is not registered', function (): void {
    BuyNotRegisteredTestPrompt::$shouldRegister = true;

    $response = ShopNotRegisteredTestPromptServer::prompt(BuyNotRegisteredTestPrompt::class);

    $response->assertNotRegistered();
})->throws(AssertionFailedError::class, 'The prompt ['.BuyNotRegisteredTestPrompt::class.'] is registered.');
