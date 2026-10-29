<?php

declare(strict_types=1);

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\ExpectationFailedException;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
}

class BookingServer extends Server
{
    protected array $tools = [
        GetBookingTool::class,
        GetBookingWithoutStructuredContentTool::class,
        GetBookingWithSerializableValuesTool::class,
        ListBookingsTool::class,
    ];
}

class ListBookingsTool extends Tool
{
    protected string $name = 'booking/list';

    protected string $title = 'List bookings';

    protected string $description = 'List bookings';

    public function handle(): ResponseFactory
    {
        return Response::structured([
            'data' => [
                ['id' => 123, 'status' => BookingStatus::Confirmed],
                ['id' => 124, 'status' => BookingStatus::Confirmed],
            ],
            'meta' => [
                'total' => 2,
            ],
        ]);
    }
}

class GetBookingWithSerializableValuesTool extends Tool
{
    protected string $name = 'booking/get/serializable';

    protected string $title = 'Get booking';

    protected string $description = 'Get a booking';

    public function handle(): ResponseFactory
    {
        return Response::structured([
            'status' => BookingStatus::Confirmed,
            'amount' => 10.0,
        ]);
    }
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

it('succeeds to assert the structured content with closure', function (): void {
    $response = BookingServer::tool(GetBookingTool::class);

    $response->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('type', 'booking')->etc());
});

it('fails to assert the structured content with closure', function (): void {
    $response = BookingServer::tool(GetBookingTool::class);

    $response->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('type', 'foo')->etc());
})->throws(ExpectationFailedException::class);

it('serializes the structured content before asserting with a closure', function (): void {
    $response = BookingServer::tool(GetBookingWithSerializableValuesTool::class);

    $response->assertStructuredContent(
        fn (AssertableJson $json): AssertableJson => $json
            ->where('status', BookingStatus::Confirmed)
            ->where('amount', 10)
            ->etc()
    );
});

it('serializes the structured content before asserting with an array', function (): void {
    $response = BookingServer::tool(GetBookingWithSerializableValuesTool::class);

    $response->assertStructuredContent([
        'status' => BookingStatus::Confirmed,
        'amount' => 10.0,
    ]);
});

it('fails to assert the structured content with a closure when it is not present', function (): void {
    $response = BookingServer::tool(GetBookingWithoutStructuredContentTool::class);

    $response->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->etc());
})->throws(ExpectationFailedException::class);

it('may assert the structured content count', function (): void {
    $response = BookingServer::tool(GetBookingTool::class);

    $response->assertStructuredContentCount(3);
});

it('may assert the structured content count at a key', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response->assertStructuredContentCount(2, 'data');
});

it('fails to assert the structured content count is wrong', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response->assertStructuredContentCount(3, 'data');
})->throws(ExpectationFailedException::class);

it('fails to assert the structured content count when it is not present', function (): void {
    $response = BookingServer::tool(GetBookingWithoutStructuredContentTool::class);

    $response->assertStructuredContentCount(0);
})->throws(ExpectationFailedException::class);

it('may assert the structured content path', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response
        ->assertStructuredContentPath('data.1.id', 124)
        ->assertStructuredContentPath('meta', ['total' => 2]);
});

it('may assert the structured content path with closure', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response->assertStructuredContentPath('meta.total', fn (int $total): bool => $total === 2);
});

it('fails to assert the structured content path is wrong', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response->assertStructuredContentPath('data.1.id', 123);
})->throws(ExpectationFailedException::class);

it('fails to assert the structured content path with closure', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response->assertStructuredContentPath('meta.total', fn (int $total): bool => $total === 3);
})->throws(ExpectationFailedException::class);

it('may assert the structured content path with an enum', function (): void {
    $response = BookingServer::tool(ListBookingsTool::class);

    $response->assertStructuredContentPath('data.0.status', BookingStatus::Confirmed);
});

it('fails to assert the structured content path when it is not present', function (): void {
    $response = BookingServer::tool(GetBookingWithoutStructuredContentTool::class);

    $response->assertStructuredContentPath('id', 123);
})->throws(ExpectationFailedException::class);
