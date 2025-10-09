<?php

use Laravel\Mcp\Server\ServerContext;

it('clamps perPage to default and max values', function (): void {
    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test',
        serverVersion: '1.0.0',
        instructions: 'x',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        resourceTemplates: [],
        prompts: [],
    );

    expect($context->perPage())->toBe(10)
        ->and($context->perPage(5))->toBe(5)
        ->and($context->perPage(10))->toBe(10)
        ->and($context->perPage(100))->toBe(50);
});
