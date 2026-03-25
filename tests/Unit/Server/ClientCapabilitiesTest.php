<?php

use Laravel\Mcp\Server\ClientCapabilities;

it('returns empty capabilities by default', function (): void {
    $capabilities = new ClientCapabilities;

    expect($capabilities->all())->toEqual([]);
});

it('can check if a top-level capability is supported', function (): void {
    $capabilities = new ClientCapabilities([
        'tools' => ['listChanged' => true],
    ]);

    expect($capabilities->supports('tools'))->toBeTrue()
        ->and($capabilities->supports('nonexistent'))->toBeFalse();
});

it('can check if an extension is supported', function (): void {
    $capabilities = new ClientCapabilities([
        'extensions' => [
            'io.modelcontextprotocol/ui' => [
                'mimeTypes' => ['text/html;profile=mcp-app'],
            ],
        ],
    ]);

    expect($capabilities->supportsExtension('io.modelcontextprotocol/ui'))->toBeTrue()
        ->and($capabilities->supportsExtension('nonexistent'))->toBeFalse();
});

it('has a convenience method for ui support', function (): void {
    $withUi = new ClientCapabilities([
        'extensions' => [
            'io.modelcontextprotocol/ui' => (object) [],
        ],
    ]);

    $withoutUi = new ClientCapabilities([
        'tools' => ['listChanged' => true],
    ]);

    expect($withUi->supportsUi())->toBeTrue()
        ->and($withoutUi->supportsUi())->toBeFalse();
});

it('returns all capabilities', function (): void {
    $caps = ['tools' => ['listChanged' => true], 'prompts' => ['listChanged' => false]];
    $capabilities = new ClientCapabilities($caps);

    expect($capabilities->all())->toEqual($caps);
});
