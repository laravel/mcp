<?php

use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Support\ValidationMessages;

test('implodes all messages a single one', function () {
    $exception = null;

    try {
        validator(
            ['name' => '', 'email' => 'invalid-email'],
            ['name' => 'required', 'email' => 'required|email']
        )->validate();
    } catch (ValidationException $e) {
        $exception = $e;
    }

    $messages = ValidationMessages::from($exception);

    expect($messages)->toBe('The name field is required. The email field must be a valid email address.');
});
