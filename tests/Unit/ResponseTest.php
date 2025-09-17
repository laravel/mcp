<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Exceptions\NotImplementedException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;

it('creates a notification response', function (): void {
    $response = Response::notification('test.method', ['key' => 'value']);

    expect($response->content())->toBeInstanceOf(Notification::class);
    expect($response->isNotification())->toBeTrue();
    expect($response->isError())->toBeFalse();
    expect($response->role())->toBe(Role::USER);
});

it('creates a text response', function (): void {
    $response = Response::text('Hello world');

    expect($response->content())->toBeInstanceOf(Text::class);
    expect($response->isNotification())->toBeFalse();
    expect($response->isError())->toBeFalse();
    expect($response->role())->toBe(Role::USER);
});

it('creates a blob response', function (): void {
    $response = Response::blob('binary content');

    expect($response->content())->toBeInstanceOf(Blob::class);
    expect($response->isNotification())->toBeFalse();
    expect($response->isError())->toBeFalse();
    expect($response->role())->toBe(Role::USER);
});

it('creates an error response', function (): void {
    $response = Response::error('Something went wrong');

    expect($response->content())->toBeInstanceOf(Text::class);
    expect($response->isNotification())->toBeFalse();
    expect($response->isError())->toBeTrue();
    expect($response->role())->toBe(Role::USER);
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

    expect($assistantResponse->content())->toBeInstanceOf(Text::class);
    expect($assistantResponse->role())->toBe(Role::ASSISTANT);
    expect($assistantResponse->isError())->toBeFalse();
});

it('preserves error state when converting to assistant role', function (): void {
    $response = Response::error('Error message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class);
    expect($assistantResponse->role())->toBe(Role::ASSISTANT);
    expect($assistantResponse->isError())->toBeTrue();
});
