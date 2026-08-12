<?php

declare(strict_types=1);

use Laravel\Mcp\Schema\Implementation;

it('parses a well formed implementation', function (): void {
    $implementation = Implementation::from([
        'name' => 'Test Server',
        'version' => '1.0.0',
        'title' => 'Testing',
        'icons' => [['src' => 'https://mcp.test/icon.png', 'theme' => 'dark']],
    ]);

    expect($implementation?->name)->toBe('Test Server')
        ->and($implementation?->title)->toBe('Testing')
        ->and($implementation?->icons[0]->src)->toBe('https://mcp.test/icon.png');
});

it('returns null rather than crashing on any malformed payload', function (mixed $payload): void {
    expect(Implementation::from($payload))->toBeNull();
})->with([
    'not an array' => 'nope',
    'missing name' => [['version' => '1.0.0']],
    'missing version' => [['name' => 'Test Server']],
    'non string name' => [['name' => 123, 'version' => '1.0.0']],
    'non string title' => [['name' => 'Test Server', 'version' => '1.0.0', 'title' => 123]],
    'non array icons' => [['name' => 'Test Server', 'version' => '1.0.0', 'icons' => 'nope']],
    'non array icon entry' => [['name' => 'Test Server', 'version' => '1.0.0', 'icons' => [1, 2]]],
    'non string icon src' => [['name' => 'Test Server', 'version' => '1.0.0', 'icons' => [['src' => 123]]]],
    'non array icon sizes' => [['name' => 'Test Server', 'version' => '1.0.0', 'icons' => [['src' => 'a', 'sizes' => 'big']]]],
]);
