<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use PHPUnit\Framework\ExpectationFailedException;

class HotelP extends Server
{
    protected array $prompts = [
        BookingPrompt::class,
    ];
}

class BookingPrompt extends Prompt
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
    $response = HotelP::prompt(BookingPrompt::class);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert that text is seen when providing arguments', function (): void {
    $response = HotelP::prompt(BookingPrompt::class, ['date' => now()->addDay()->toDateString()]);

    $response->assertSee('Your booking is confirmed!');
});

it('may assert that text is seen when providing arguments that are wrong', function (): void {
    $response = HotelP::prompt(BookingPrompt::class, ['date' => now()->subDay()->toDateString()]);

    $response
        ->assertSee('The booking date cannot be in the past.');
});

it('fails to assert that text is seen when not present', function (): void {
    $response = HotelP::prompt(BookingPrompt::class);

    $response->assertSee('This text is not present');
})->throws(ExpectationFailedException::class);

it('may assert that text is seen when returning array content', function (): void {
    $response = HotelP::prompt(BookingPrompt::class, ['date' => '2999-01-01']);

    $response
        ->assertSee('That date is too far in the future')
        ->assertSee('Please select a more reasonable date.');
});

it('fails if the prompt is not registered', function (): void {
    $response = HotelP::prompt(new class extends Prompt
    {
        protected string $name = 'unknown/prompt';

        public function handle(): string
        {
            return 'This should not be possible.';
        }
    });

    $response->assertHasErrors([
        'Prompt [unknown/prompt] not found.',
    ]);
});

it('fails if the prompt is not registered due the should register method', function (): void {
    $response = HotelP::prompt(BookingPrompt::class, ['register' => false]);

    $response->assertHasErrors([
        'Prompt [booking-prompt] not found',
    ]);
});
