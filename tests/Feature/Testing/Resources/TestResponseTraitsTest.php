<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;

class LibraryR extends Server
{
    protected array $tools = [
        LibraryTool::class,
    ];
}

class LibraryResource extends Tool
{
    public function handle(Request $request): string
    {
        return 'You may borrow books.';
    }
}

test('macroable', function (): void {
    TestResponse::macro('foo', fn (): string => 'bar');

    $response = LibraryR::resource(LibraryResource::class);

    expect($response->foo())->toBe('bar');
});

it('supports conditionals', function (): void {
    $response = LibraryR::resource(LibraryResource::class);

    $whenTrue = false;
    $response->when(true, function () use (&$whenTrue): void {
        $whenTrue = true;
    });
    expect($whenTrue)->toBeTrue();

    $whenFalse = false;
    $response->when(false, function () use (&$whenFalse): void {
        $whenFalse = true;
    });
    expect($whenFalse)->toBeFalse();

    $unlessTrue = false;
    $response->unless(true, function () use (&$unlessTrue): void {
        $unlessTrue = true;
    });
    expect($unlessTrue)->toBeFalse();

    $unlessFalse = false;
    $response->unless(false, function () use (&$unlessFalse): void {
        $unlessFalse = true;
    });
    expect($unlessFalse)->toBeTrue();
});
