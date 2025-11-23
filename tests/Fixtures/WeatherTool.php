<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class WeatherTool extends Tool
{
    protected string $description = 'Get current weather data for a location';

    protected string $title = 'Weather Data Retriever';

    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'location' => 'required|string',
        ]);

        return Response::structured([
            'temperature' => 22.5,
            'conditions' => 'Partly cloudy',
            'humidity' => 65,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'location' => $schema->string()->description('City name or zip code')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'temperature' => $schema->number()->description('Temperature in celsius')->required(),
            'conditions' => $schema->string()->description('Weather conditions description')->required(),
            'humidity' => $schema->number()->description('Humidity percentage')->required(),
        ];
    }
}
