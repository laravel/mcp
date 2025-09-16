<?php

use Illuminate\Foundation\Auth\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\ExpectationFailedException;

class Airport extends Server
{
    protected array $tools = [
        TicketTool::class,
    ];
}

class TicketTool extends Tool
{
    public function handle(Request $request): string
    {
        return $request->user() instanceof \Illuminate\Contracts\Auth\Authenticatable ? 'Here is your ticket!' : 'You must be logged in to get a ticket.';
    }
}

it('may assert the user is acting as the given user', function (): void {
    $user = new class extends User {};

    $response = Airport::actingAs($user)
        ->tool(TicketTool::class);

    $response->assertText('Here is your ticket!');
});

it('may assert the user is not acting as a user', function (): void {
    $response = Airport::tool(TicketTool::class);

    $response->assertText('You must be logged in to get a ticket.');
});

it('may assert authenticated and authenticated as a specific user', function (): void {
    $user = new class extends User
    {
        public int $id = 1;
    };

    $response = Airport::actingAs($user)
        ->tool(TicketTool::class);

    $response->assertAuthenticated()
        ->assertAuthenticatedAs($user);
});

it('may assert guest when no user is authenticated', function (): void {
    $response = Airport::tool(TicketTool::class);

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

    $response = Airport::actingAs($userA)
        ->tool(TicketTool::class);

    $response->assertAuthenticatedAs($userB);
})->throws(ExpectationFailedException::class);
