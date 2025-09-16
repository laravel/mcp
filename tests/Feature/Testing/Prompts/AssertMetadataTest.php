<?php

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use PHPUnit\Framework\ExpectationFailedException;

class GarageA extends Server
{
    protected array $prompts = [
        InspectionPrompt::class,
    ];
}

class InspectionPrompt extends Prompt
{
    protected string $name = 'car/inspection';

    protected string $title = 'Car Inspection';

    protected string $description = 'Schedule a car inspection';

    public function handle(): string
    {
        return 'Your car inspection is scheduled!';
    }
}

it('may assert the title', function (): void {
    $response = GarageA::prompt(InspectionPrompt::class);

    $response->assertName('car/inspection')
        ->assertTitle('Car Inspection')
        ->assertDescription('Schedule a car inspection');
});

it('fails to assert the name is wrong', function (): void {
    $response = GarageA::prompt(InspectionPrompt::class);

    $response->assertName('wrong/name');
})->throws(ExpectationFailedException::class);

it('fails to assert the title is wrong', function (): void {
    $response = GarageA::prompt(InspectionPrompt::class);

    $response->assertTitle('Wrong Title');
})->throws(ExpectationFailedException::class);

it('fails to assert the description is wrong', function (): void {
    $response = GarageA::prompt(InspectionPrompt::class);

    $response->assertDescription('Wrong description');
})->throws(ExpectationFailedException::class);
