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
        ServiceConfirmationCheckResource::class,
        InvalidResourceTemplateResource::class,
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

class ServiceConfirmationCheckResource extends Resource
{
    protected string $uri = 'service://confirmation-check/{type}';

    public function handle(Request $request): string
    {
        $type = $request->get('type');

        if (in_array($type, ['restaurant', 'massage'], true)) {
            return "Sorry, we could not the reservation for your {$type}.";
        }

        return "Your {$type} reservation is confirmed!";
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->boolean('register', true);
    }
}

class InvalidResourceTemplateResource extends Resource
{
    protected string $uri = 'invalid://optional-param/{param?}';

    public function handle(Request $request): string
    {
        $param = $request->get('param');

        return "Oops! something happened because optional param [{$param}] on resource templates are not allowed.";
    }

    public function shouldRegister(Request $request): bool
    {
        return $request->boolean('register', true);
    }
}

it('may assert that text is seen when returning string content', function (): void {
    $response = HotelR::resource(BookingResource::class);

    $response->assertSee('Your booking is confirmed!')
        ->assertDontSee('The booking date cannot be in the past.')
        ->assertDontSee('Please select a more reasonable date.');
});

it('may assert that text is seen when providing arguments', function (): void {
    $response = HotelR::resource(BookingResource::class, ['date' => now()->addDay()->toDateString()]);

    $response->assertSee('Your booking is confirmed!')
        ->assertDontSee('The booking date cannot be in the past.')
        ->assertDontSee('Please select a more reasonable date.');
});

it('may assert that text is seen when using templates', function (string $uri, string $message): void {
    $response = HotelR::resource(ServiceConfirmationCheckResource::class, ['uri' => $uri]);

    $response
        ->assertSee($message)
        ->assertSee($uri)
        ->assertUri('service://confirmation-check/{type}');
})->with([
    ['service://confirmation-check/spa', 'Your spa reservation is confirmed!'],
    ['service://confirmation-check/beach-dinner', 'Your beach-dinner reservation is confirmed!'],
    ['service://confirmation-check/restaurant', 'Sorry, we could not the reservation for your restaurant.'],
    ['service://confirmation-check/massage', 'Sorry, we could not the reservation for your massage.'],
]);

it('may assert that resource is not found when using templates and not matching uris', function (): void {
    $uri = 'service://confirmation-check/spa/extra-value';

    $response = HotelR::resource(ServiceConfirmationCheckResource::class, ['uri' => $uri]);

    $response
        ->assertHasErrors(["Resource [{$uri}] not found."]);
});


it('may assert that text is seen when providing arguments that are wrong', function (): void {
    $response = HotelR::resource(BookingResource::class, ['date' => now()->subDay()->toDateString()]);

    $response
        ->assertSee('The booking date cannot be in the past.')
        ->assertDontSee('Your booking is confirmed!')
        ->assertDontSee('Please select a more reasonable date.');
});

it('fails to assert that text is seen when not present', function (): void {
    $response = HotelR::resource(BookingResource::class);

    $response->assertSee('This text is not present');
})->throws(ExpectationFailedException::class);

it('fails to assert that text is seen when optional params are used for the resource uri', function (): void {
    $response = HotelR::resource(ServiceConfirmationCheckResource::class, ['uri' => 'invalid://optional-param/value']);

    $response->assertSee('Oops! something happened because optional param [value] on resource templates are not allowed.');
})->throws(ExpectationFailedException::class);

it('fails to assert that text is not seen when it is present', function (): void {
    $response = HotelR::resource(BookingResource::class);

    $response->assertDontSee('Your booking is confirmed!');
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
