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

    public function messages(): array
    {
        return [
            'name.required' => 'The :attribute field is required.',
            'name.string' => 'The :attribute must be a string.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
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

        return "Hello, {$name}!";
    }
}

it('can use the custom request validation', function (): void {
    $response = GreetingServer::tool(Greet::class, [
        'name' => 'World',
    ]);

    $response->assertSee('Hello, World!');
});

it('can throw validation errors when required fields are missing', function (): void {
    $response = GreetingServer::tool(Greet::class, []);

    $response->assertHasErrors(['name' => 'The Name field is required.']);
});
