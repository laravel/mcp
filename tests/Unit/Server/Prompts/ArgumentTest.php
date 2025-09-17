<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Prompts\Argument;

it('creates an argument with required parameters', function (): void {
    $argument = new Argument(
        name: 'username',
        description: 'The username to authenticate with'
    );

    expect($argument->name)->toBe('username');
    expect($argument->description)->toBe('The username to authenticate with');
    expect($argument->required)->toBeFalse();
});

it('creates an argument with all parameters', function (): void {
    $argument = new Argument(
        name: 'password',
        description: 'The password to authenticate with',
        required: true
    );

    expect($argument->name)->toBe('password');
    expect($argument->description)->toBe('The password to authenticate with');
    expect($argument->required)->toBeTrue();
});

it('converts to array correctly', function (): void {
    $argument = new Argument(
        name: 'api_key',
        description: 'The API key for authentication',
        required: true
    );

    expect($argument->toArray())->toBe([
        'name' => 'api_key',
        'description' => 'The API key for authentication',
        'required' => true,
    ]);
});

it('converts optional argument to array correctly', function (): void {
    $argument = new Argument(
        name: 'format',
        description: 'The output format'
    );

    expect($argument->toArray())->toBe([
        'name' => 'format',
        'description' => 'The output format',
        'required' => false,
    ]);
});

it('implements Arrayable interface', function (): void {
    $argument = new Argument(
        name: 'test',
        description: 'Test argument'
    );

    expect($argument)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
});
