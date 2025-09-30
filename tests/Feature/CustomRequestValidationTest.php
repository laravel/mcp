<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

class GreetingRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }
}

class GreetingServer extends Server
{
    protected array $tools = [
        Greet::class,
    ];
}

class Greet extends Tool
{
    public function handle(GreetingRequest $request): string
    {
        $validated = $request->validated();
        $name = $validated['name'];
        $response = "Hello, {$name}!";
        return $response;
    }
}

it('can use the custom request validation', function (): void {
    $response = GreetingServer::tool(Greet::class, [
        'name' => 'World',
    ]);

    $response->assertSee('Hello, World!');
});

it('can throw validation errors', function (): void {
    $response = GreetingServer::tool(Greet::class, []);

    $response->assertHasErrors();
});
