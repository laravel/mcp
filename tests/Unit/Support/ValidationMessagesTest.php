<?php

use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Support\ValidationMessages;

test('implodes all messages a single one', function (): void {
    $exception = null;

    try {
        validator(
            ['name' => '', 'email' => 'invalid-email'],
            ['name' => 'required', 'email' => 'required|email']
        )->validate();
    } catch (ValidationException $validationException) {
        $exception = $validationException;
    }

    $messages = ValidationMessages::from($exception);

    expect($messages)->toBe('The name field is required. The email field must be a valid email address.');
});

test('returns a generic message if no messages are available', function (): void {
    $exception = new ValidationException(validator([], []));

    $messages = ValidationMessages::from($exception);

    expect($messages)->toBe('The given data was invalid.');
});
