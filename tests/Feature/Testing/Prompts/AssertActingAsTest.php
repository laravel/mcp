<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use PHPUnit\Framework\ExpectationFailedException;

class AirCompany extends Server
{
    protected array $prompts = [
        TicketPrompt::class,
    ];
}

class TicketPrompt extends Prompt
{
    public function handle(Request $request): string
    {
        return $request->user() instanceof Authenticatable
            ? 'Here is your ticket!'
            : 'You must be logged in to get a ticket.';
    }
}

it('may assert the user is acting as the given user', function (): void {
    $user = new class extends User {};

    $response = AirCompany::actingAs($user)
        ->prompt(TicketPrompt::class);

    $response->assertText('Here is your ticket!');
});

it('may assert the user is not acting as a user', function (): void {
    $response = AirCompany::prompt(TicketPrompt::class);

    $response->assertText('You must be logged in to get a ticket.');
});

it('may assert authenticated and authenticated as a specific user', function (): void {
    $user = new class extends User
    {
        public int $id = 1;
    };

    $response = AirCompany::actingAs($user)
        ->prompt(TicketPrompt::class);

    $response->assertAuthenticated()
        ->assertAuthenticatedAs($user);
});

it('may assert guest when no user is authenticated', function (): void {
    $response = AirCompany::prompt(TicketPrompt::class);

    $response->assertGuest();
});

it('fails when asserting authenticated as a different user', function (): void {
    $userA = new class extends User
    {
        public int $id = 1;
    };

    $userB = new class extends User
    {
        public int $id = 2;
    };

    $response = AirCompany::actingAs($userA)
        ->prompt(TicketPrompt::class);

    $response->assertAuthenticatedAs($userB);
})->throws(ExpectationFailedException::class);
