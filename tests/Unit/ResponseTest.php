<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Exceptions\NotImplementedException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;

it('creates a notification response', function (): void {
    $response = Response::notification('test.method', ['key' => 'value']);

    expect($response->content())->toBeInstanceOf(Notification::class)
        ->and($response->isNotification())->toBeTrue()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a text response', function (): void {
    $response = Response::text('Hello world');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a blob response', function (): void {
    $response = Response::blob('binary content');

    expect($response->content())->toBeInstanceOf(Blob::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an error response', function (): void {
    $response = Response::error('Something went wrong');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeTrue()
        ->and($response->role())->toBe(Role::User);
});

it('throws exception for audio method', function (): void {
    expect(function (): void {
        Response::audio();
    })->toThrow(NotImplementedException::class, 'The method ['.\Laravel\Mcp\Response::class.'@'.\Laravel\Mcp\Response::class.'::audio] is not implemented yet.');
});

it('throws exception for image method', function (): void {
    expect(function (): void {
        Response::image();
    })->toThrow(NotImplementedException::class, 'The method ['.\Laravel\Mcp\Response::class.'@'.\Laravel\Mcp\Response::class.'::image] is not implemented yet.');
});

it('can convert response to assistant role', function (): void {
    $response = Response::text('Original message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class)
        ->and($assistantResponse->role())->toBe(Role::Assistant)
        ->and($assistantResponse->isError())->toBeFalse();
});

it('preserves error state when converting to assistant role', function (): void {
    $response = Response::error('Error message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class)
        ->and($assistantResponse->role())->toBe(Role::Assistant)
        ->and($assistantResponse->isError())->toBeTrue();
});

it('creates a json response', function (): void {
    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});

it('handles nested arrays in json response', function (): void {
    $data = [
        'user' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'roles' => ['admin', 'developer'],
        ],
        'active' => true,
    ];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});

it('throws JsonException for invalid json data', function (): void {
    $data = ['invalid' => INF];

    expect(function () use ($data): void {
        Response::json($data);
    })->toThrow(JsonException::class);
});

it('handles empty array in json response', function (): void {
    $data = [];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
});

it('creates text response with content meta', function (): void {
    $response = Response::text('Hello')->withMeta(['author' => 'John']);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['author' => 'John']);
});

it('creates blob response with content meta', function (): void {
    $response = Response::blob('binary')->withMeta(['encoding' => 'utf-8']);

    expect($response->content())->toBeInstanceOf(Blob::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['encoding' => 'utf-8']);
});

it('creates a notification response with content meta', function (): void {
    $response = Response::notification('test/event', ['data' => 'value'])->withMeta(['author' => 'system']);

    expect($response->content())->toBeInstanceOf(Notification::class)
        ->and($response->content()->toArray()['params'])->toHaveKey('_meta')
        ->and($response->content()->toArray()['params']['_meta'])->toEqual(['author' => 'system']);
});

it('throws exception when array contains a non-Response object', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::text('Valid'),
        'Invalid string',
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when array contains nested ResponseFactory', function (): void {
    $nestedFactory = Response::make(Response::text('Nested'));

    expect(fn (): ResponseFactory => Response::make([
        Response::text('First'),
        $nestedFactory,
        Response::text('Third'),
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when an array contains null', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::text('Valid'),
        null,
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('creates json response with pretty printing when config is true', function (): void {
    config(['mcp.pretty_json' => true]);

    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);
    
    $content = (string) $response->content();
    expect($content)->toBe(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))
        ->and($content)->toContain("\n")
        ->and($content)->toContain('    ');
});

it('creates json response without pretty printing when config is false', function (): void {
    config(['mcp.pretty_json' => false]);

    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);
    
    $content = (string) $response->content();
    $expectedFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    expect($content)->toBe(json_encode($data, $expectedFlags))
        ->and($content)->not->toContain("\n")
        ->and($content)->toBe('{"key":"value","number":123}');
});

it('compact json reduces token usage significantly', function (): void {
    $data = [
        'user_id' => '42',
        'name' => 'S2S token',
        'project_roles' => [
            ['project_id' => '1', 'project_name' => 'Project A', 'role_name' => 'owner'],
        ],
    ];

    // Pretty version
    config(['mcp.pretty_json' => true]);
    $prettyResponse = Response::json($data);
    $prettyContent = (string) $prettyResponse->content();

    // Compact version
    config(['mcp.pretty_json' => false]);
    $compactResponse = Response::json($data);
    $compactContent = (string) $compactResponse->content();

    $prettySizeKb = strlen($prettyContent);
    $compactSizeKb = strlen($compactContent);
    $savings = round((1 - $compactSizeKb / $prettySizeKb) * 100, 1);

    expect($compactSizeKb)->toBeLessThan($prettySizeKb)
        ->and($savings)->toBeGreaterThan(30); // At least 30% savings
});
