<?php

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Resource;
use PHPUnit\Framework\ExpectationFailedException;

class GarageR extends Server
{
    protected array $resources = [
        InspectionResource::class,
    ];
}

class InspectionResource extends Resource
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
    $response = GarageR::resource(InspectionResource::class);

    $response->assertName('car/inspection')
        ->assertTitle('Car Inspection')
        ->assertDescription('Schedule a car inspection');
});

it('fails to assert the name is wrong', function (): void {
    $response = GarageR::resource(InspectionResource::class);

    $response->assertName('wrong/name');
})->throws(ExpectationFailedException::class);

it('fails to assert the title is wrong', function (): void {
    $response = GarageR::resource(InspectionResource::class);

    $response->assertTitle('Wrong Title');
})->throws(ExpectationFailedException::class);

it('fails to assert the description is wrong', function (): void {
    $response = GarageR::resource(InspectionResource::class);

    $response->assertDescription('Wrong description');
})->throws(ExpectationFailedException::class);
