<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

it('may return all data', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->all())->toBe([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);
});

it('may return specific set of keys', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->all(['name', 'age']))->toBe([
        'name' => 'Alice',
        'age' => 30,
    ])->and($request->all('name'))->toBe([
        'name' => 'Alice',
    ]);
});

it('interact with data', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->get('name'))->toBe('Alice')
        ->and($request->filled('name'))->toBeTrue()
        ->and($request->filled('country'))->toBeFalse()
        ->and($request->string('city')->value())->toBe('Wonderland')
        ->and($request->integer('city'))->toBe(0);
});

it('may be returned as array', function (): void {
    $request = new Request([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);

    expect($request->toArray())->toBe([
        'name' => 'Alice',
        'age' => 30,
        'city' => 'Wonderland',
    ]);
});

it('may return the current logged in user', function (): void {
    $user = new class extends User {};

    Auth::setUser($user);

    $request = new Request;

    expect($request->user())->toBe($user);
});

it('may return null if no user is logged in', function (): void {
    $request = new Request;

    expect($request->user())->toBeNull();
});

it('validates and returns only validated data on success', function (): void {
    $request = new Request([
        'email' => 'alice@example.com',
        'extra' => 'keep out',
    ]);

    $validated = $request->validate([
        'email' => 'required|email',
    ]);

    expect($validated)->toBe([
        'email' => 'alice@example.com',
    ]);
});

it('throws ValidationException with custom messages and attributes', function (): void {
    $request = new Request([
        'email' => 'not-an-email',
    ]);

    $messages = [
        'email.email' => 'Please provide a valid :attribute.',
    ];

    $attributes = [
        'email' => 'email address',
    ];

    $closure = function () use ($request, $messages, $attributes): void {
        $request->validate([
            'email' => 'required|email',
        ], $messages, $attributes);
    };

    expect($closure)->toThrow(ValidationException::class);
});

it('can get uri when set via constructor', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        sessionId: 'session-123',
        meta: ['key' => 'value'],
        uri: 'file://resources/example'
    );

    expect($request->uri())->toBe('file://resources/example');
});

it('returns null for uri when not set via constructor', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        sessionId: 'session-123',
        meta: ['key' => 'value']
    );

    expect($request->uri())->toBeNull();
});

it('returns null for uri when explicitly set to null in constructor', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        uri: null
    );

    expect($request->uri())->toBeNull();
});

it('can set uri using setUri method', function (): void {
    $request = new Request(['name' => 'Alice']);

    $result = $request->setUri('file://resources/test');

    expect($request->uri())->toBe('file://resources/test');
});

it('can update uri using setUri method', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        uri: 'file://resources/original'
    );

    $request->setUri('file://resources/updated');

    expect($request->uri())->toBe('file://resources/updated');
});

it('can set uri to null using setUri method', function (): void {
    $request = new Request(
        arguments: ['name' => 'Alice'],
        uri: 'file://resources/example'
    );

    $result = $request->setUri(null);

    expect($request->uri())->toBeNull()
        ->and($result)->toBeNull();
});

it('supports method chaining with merge and setUri', function (): void {
    $request = new Request(['name' => 'Alice']);

    $request->merge(['age' => 30])->setUri('file://resources/test');

    expect($request->uri())->toBe('file://resources/test')
        ->and($request->get('name'))->toBe('Alice')
        ->and($request->get('age'))->toBe(30);
});
