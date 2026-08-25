<?php

declare(strict_types=1);

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use PHPUnit\Framework\AssertionFailedError;

class GarageListPromptsServer extends Server
{
    public int $defaultPaginationLength = 1;

    protected array $prompts = [
        OilChangePrompt::class,
        TireRotationPrompt::class,
    ];
}

class OilChangePrompt extends Prompt
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

class TireRotationPrompt extends Prompt
{
    protected string $name = 'garage/tire-rotation';

    public function handle(): string
    {
        return 'Tires rotated.';
    }
}

it('may assert that prompts are registered on a server', function (): void {
    OilChangePrompt::$shouldRegister = true;

    GarageListPromptsServer::prompts()->assertRegistered([
        OilChangePrompt::class,
        TireRotationPrompt::class,
    ]);
});

it('may fail to assert that prompts are registered on a server', function (): void {
    OilChangePrompt::$shouldRegister = false;

    GarageListPromptsServer::prompts()->assertRegistered([
        TireRotationPrompt::class,
        OilChangePrompt::class,
    ]);
})->throws(AssertionFailedError::class, 'The expected class ['.OilChangePrompt::class.'] was not found in the response content.');

it('may assert that prompts are not registered on a server', function (): void {
    OilChangePrompt::$shouldRegister = false;

    GarageListPromptsServer::prompts()->assertRegistered([
        TireRotationPrompt::class,
    ])->assertNotRegistered([
        OilChangePrompt::class,
    ]);
});

it('may fail to assert that prompts are not registered on a server', function (): void {
    OilChangePrompt::$shouldRegister = true;

    GarageListPromptsServer::prompts()->assertRegistered([
        TireRotationPrompt::class,
    ])->assertNotRegistered([
        OilChangePrompt::class,
    ]);
})->throws(AssertionFailedError::class, 'The expected class ['.OilChangePrompt::class.'] was found in the response content.');
