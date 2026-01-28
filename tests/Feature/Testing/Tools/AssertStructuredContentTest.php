<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\ExpectationFailedException;

class BookingServer extends Server
{
    protected array $tools = [
        GetBookingTool::class,
        GetBookingWithoutStructuredContentTool::class,
    ];
}

class GetBookingTool extends Tool
{
    protected string $name = 'booking/get';

    protected string $title = 'Get booking';

    protected string $description = 'Get a booking';

    public function handle(): ResponseFactory
    {
        return Response::structured([
            'type' => 'booking',
            'id' => 123,
            'status' => 'confirmed',
        ]);
    }
}

class GetBookingWithoutStructuredContentTool extends Tool
{
    protected string $name = 'booking/get/no-structured';

    protected string $title = 'Get booking';

    protected string $description = 'Get a booking';

    public function handle(): Response
    {
        return Response::text('This is the confirmed booking 123');
    }
}

it('may assert the structured content', function (): void {
    $response = BookingServer::tool(GetBookingTool::class);

    $response->assertStructuredContent([
        'type' => 'booking',
        'id' => 123,
        'status' => 'confirmed',
    ]);
});

it('fails to assert the structured content is wrong', function (): void {
    $response = BookingServer::tool(GetBookingTool::class);

    $response->assertStructuredContent([
        'type' => 'booking',
        'id' => 124,
        'status' => 'pending',
    ]);
})->throws(ExpectationFailedException::class);

it('fails to assert the structured content is not present', function (): void {
    $response = BookingServer::tool(GetBookingWithoutStructuredContentTool::class);

    $response->assertStructuredContent([
        'type' => 'booking',
        'id' => 123,
        'status' => 'confirmed',
    ]);
})->throws(ExpectationFailedException::class);
