<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Exceptions\NotImplementedException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\LogNotification;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Enums\LogLevel;

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
    })->toThrow(NotImplementedException::class, 'The method ['.Response::class.'@'.Response::class.'::audio] is not implemented yet.');
});

it('throws exception for image method', function (): void {
    expect(function (): void {
        Response::image();
    })->toThrow(NotImplementedException::class, 'The method ['.Response::class.'@'.Response::class.'::image] is not implemented yet.');
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

it('creates a log response', function (): void {
    $response = Response::log(LogLevel::Error, 'Something went wrong');

    expect($response->content())->toBeInstanceOf(LogNotification::class)
        ->and($response->isNotification())->toBeTrue()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a log response with logger name', function (): void {
    $response = Response::log(LogLevel::Info, 'Query executed', 'database');

    expect($response->content())->toBeInstanceOf(LogNotification::class);

    $content = $response->content();
    expect($content->logger())->toBe('database');
});

it('creates a log response with array data', function (): void {
    $data = ['error' => 'Connection failed', 'host' => 'localhost'];
    $response = Response::log(LogLevel::Error, $data);

    expect($response->content())->toBeInstanceOf(LogNotification::class);

    $content = $response->content();
    expect($content->data())->toBe($data);
});

it('throws exception for invalid log data', function (): void {
    $data = ['invalid' => INF];

    expect(function () use ($data): void {
        Response::log(LogLevel::Error, $data);
    })->toThrow(InvalidArgumentException::class, 'Invalid log data:');
});

it('creates log response with meta', function (): void {
    $response = Response::log(LogLevel::Warning, 'Low memory')
        ->withMeta(['trace_id' => 'abc123']);

    expect($response->content()->toArray()['params'])->toHaveKey('_meta')
        ->and($response->content()->toArray()['params']['_meta'])->toEqual(['trace_id' => 'abc123']);
});
