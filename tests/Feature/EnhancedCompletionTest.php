<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\SupportsCompletion;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

enum TestUnits: string
{
    case Celsius = 'celsius';
    case Fahrenheit = 'fahrenheit';
    case Kelvin = 'kelvin';
}

enum TestStatusEnum
{
    case Active;
    case Inactive;
    case Pending;
}

class EnhancedCompletionServer extends Server
{
    protected string $name = 'Enhanced Completion Server';

    protected array $capabilities = [
        'completions' => [],
    ];

    protected array $prompts = [
        LocationPrompt::class,
        UnitsPrompt::class,
        StatusPrompt::class,
        DynamicPrompt::class,
        SingleStringPrompt::class,
    ];
}

class LocationPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Select a location';

    public function arguments(): array
    {
        return [
            new Argument('location', 'Location name', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResponse
    {
        return match ($argument) {
            'location' => CompletionResponse::usingList([
                'New York',
                'Los Angeles',
                'Chicago',
                'Houston',
                'Miami',
            ]),
            default => CompletionResponse::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("Selected: {$request->get('location')}");
    }
}

class UnitsPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Select temperature unit';

    public function arguments(): array
    {
        return [
            new Argument('unit', 'Temperature unit', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResponse
    {
        return match ($argument) {
            'unit' => CompletionResponse::usingEnum(TestUnits::class),
            default => CompletionResponse::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("Unit: {$request->get('unit')}");
    }
}

class StatusPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Select status';

    public function arguments(): array
    {
        return [
            new Argument('status', 'Status value', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResponse
    {
        return match ($argument) {
            'status' => CompletionResponse::usingEnum(TestStatusEnum::class),
            default => CompletionResponse::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("Status: {$request->get('status')}");
    }
}

class DynamicPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Dynamic completion';

    public function arguments(): array
    {
        return [
            new Argument('city', 'City name', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResponse
    {
        return match ($argument) {
            'city' => CompletionResponse::using(fn (string $value): \Laravel\Mcp\Server\Completions\CompletionResponse => CompletionResponse::make([
                'San Francisco',
                'San Diego',
                'San Jose',
            ])),
            default => CompletionResponse::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("City: {$request->get('city')}");
    }
}

class SingleStringPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Single string completion';

    public function arguments(): array
    {
        return [
            new Argument('name', 'Name', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResponse
    {
        return match ($argument) {
            'name' => CompletionResponse::using(fn (string $value): \Laravel\Mcp\Server\Completions\CompletionResponse => CompletionResponse::make('John Doe')),
            default => CompletionResponse::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("Name: {$request->get('name')}");
    }
}

describe('usingList() Completions', function (): void {
    it('returns all locations when no prefix provided', function (): void {
        EnhancedCompletionServer::completion(LocationPrompt::class, 'location', '')
            ->assertCompletionCount(5);
    });

    it('filters locations by prefix', function (): void {
        EnhancedCompletionServer::completion(LocationPrompt::class, 'location', 'New')
            ->assertHasCompletions(['New York'])
            ->assertCompletionCount(1);
    });

    it('filters locations case-insensitively', function (): void {
        EnhancedCompletionServer::completion(LocationPrompt::class, 'location', 'los')
            ->assertHasCompletions(['Los Angeles'])
            ->assertCompletionCount(1);
    });

    it('returns empty for non-matching prefix', function (): void {
        EnhancedCompletionServer::completion(LocationPrompt::class, 'location', 'xyz')
            ->assertCompletionCount(0);
    });
});

describe('usingEnum() Completions', function (): void {
    it('returns all backed enum values', function (): void {
        EnhancedCompletionServer::completion(UnitsPrompt::class, 'unit', '')
            ->assertHasCompletions(['celsius', 'fahrenheit', 'kelvin'])
            ->assertCompletionCount(3);
    });

    it('filters backed enum values by prefix', function (): void {
        EnhancedCompletionServer::completion(UnitsPrompt::class, 'unit', 'kel')
            ->assertHasCompletions(['kelvin'])
            ->assertCompletionCount(1);
    });

    it('returns all non-backed enum names', function (): void {
        EnhancedCompletionServer::completion(StatusPrompt::class, 'status', '')
            ->assertHasCompletions(['Active', 'Inactive', 'Pending'])
            ->assertCompletionCount(3);
    });

    it('filters non-backed enum names by prefix', function (): void {
        EnhancedCompletionServer::completion(StatusPrompt::class, 'status', 'Pen')
            ->assertHasCompletions(['Pending'])
            ->assertCompletionCount(1);
    });
});

describe('using() Callback Completions', function (): void {
    it('returns values from callback', function (): void {
        EnhancedCompletionServer::completion(DynamicPrompt::class, 'city', '')
            ->assertHasCompletions(['San Francisco', 'San Diego', 'San Jose'])
            ->assertCompletionCount(3);
    });

    it('supports single string result', function (): void {
        EnhancedCompletionServer::completion(SingleStringPrompt::class, 'name', '')
            ->assertHasCompletions(['John Doe'])
            ->assertCompletionCount(1);
    });
});
