<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\ExpectationFailedException;

class HotelT extends Server
{
    protected array $tools = [
        BookingTool::class,
    ];
}

class BookingTool extends Tool
{
    public function handle(Request $request): Response|array|string
    {
        $date = $request->date('date');

        if ($date?->isPast()) {
            return Response::error('The booking date cannot be in the past.');
        }

        if ($date?->year === 2999) {
            return [
                'You must be joking! That date is too far in the future.',
                'Please select a more reasonable date.',
            ];
        }

        return 'Your booking is confirmed!';
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->boolean('register', true);
    }
}

it('may assert that text is seen when returning string content', function (): void {
    $response = HotelT::tool(BookingTool::class);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert that text is seen when providing arguments', function (): void {
    $response = HotelT::tool(BookingTool::class, ['date' => now()->addDay()->toDateString()]);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert that text is seen when providing arguments that are wrong', function (): void {
    $response = HotelT::tool(BookingTool::class, ['date' => now()->subDay()->toDateString()]);

    $response
        ->assertSee('The booking date cannot be in the past.');
});

it('fails to assert that text is seen when not present', function (): void {
    $response = HotelT::tool(BookingTool::class);

    $response->assertSee('This text is not present');
})->throws(ExpectationFailedException::class);

it('may assert that text is seen when returning array content', function (): void {
    $response = HotelT::tool(BookingTool::class, ['date' => '2999-01-01']);

    $response
        ->assertSee('That date is too far in the future')
        ->assertSee('Please select a more reasonable date.');
});

it('fails if the tool is not registered', function (): void {
    HotelT::tool(new class extends Tool
    {
        protected string $name = 'unknown/tool';

        public function handle(): string
        {
            return 'This should not be possible.';
        }
    });
})->throws(JsonRpcException::class, 'Tool [unknown/tool] not found.');

it('fails if the tool is not registered due the should register method', function (): void {
    HotelT::tool(BookingTool::class, ['register' => false]);
})->throws(JsonRpcException::class, 'Tool [booking-tool] not found.');
