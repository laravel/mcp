<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\NotImplementedException;

it('creates exception for unimplemented method', function (): void {
    $exception = NotImplementedException::forMethod('TestClass', 'testMethod');

    expect($exception)->toBeInstanceOf(NotImplementedException::class);
    expect($exception->getMessage())->toBe('The method [TestClass@testMethod] is not implemented yet.');
});

it('can be thrown like any exception', function (): void {
    expect(function (): void {
        throw NotImplementedException::forMethod('MyClass', 'myMethod');
    })->toThrow(NotImplementedException::class, 'The method [MyClass@myMethod] is not implemented yet.');
});

it('extends base exception class', function (): void {
    $exception = NotImplementedException::forMethod('SomeClass', 'someMethod');

    expect($exception)->toBeInstanceOf(Exception::class);
});
