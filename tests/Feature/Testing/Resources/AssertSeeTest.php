<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Resource;
use PHPUnit\Framework\ExpectationFailedException;

class HotelR extends Server
{
    protected array $resources = [
        BookingResource::class,
    ];
}

class BookingResource extends Resource
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
    $response = HotelR::resource(BookingResource::class);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert that text is seen when providing arguments', function (): void {
    $response = HotelR::resource(BookingResource::class, ['date' => now()->addDay()->toDateString()]);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert that text is seen when providing arguments that are wrong', function (): void {
    $response = HotelR::resource(BookingResource::class, ['date' => now()->subDay()->toDateString()]);

    $response
        ->assertSee('The booking date cannot be in the past.');
});

it('fails to assert that text is seen when not present', function (): void {
    $response = HotelR::resource(BookingResource::class);

    $response->assertSee('This text is not present');
})->throws(ExpectationFailedException::class);

it('may assert that text is seen when returning array content', function (): void {
    $response = HotelR::resource(BookingResource::class, ['date' => '2999-01-01']);

    $response
        ->assertSee('That date is too far in the future')
        ->assertSee('Please select a more reasonable date.');
});

it('fails if the resource is not registered', function (): void {
    $response = HotelR::resource(new class extends Resource
    {
        protected string $uri = 'file://resources/hotel.md';

        public function handle(): string
        {
            return 'This should not be possible.';
        }
    });

    $response->assertHasErrors([
        'Resource [file://resources/hotel.md] not found.',
    ]);
});

it('fails if the resource is not registered due the should register method', function (): void {
    $response = HotelR::resource(BookingResource::class, ['register' => false]);

    $response->assertHasErrors([
        'Resource [file://resources/booking-resource] not found.',
    ]);
});
