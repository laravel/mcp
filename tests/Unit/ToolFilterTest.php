<?php

declare(strict_types=1);

use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Server\ServerContext;
use Tests\Fixtures\SayHiTool;
use Tests\Fixtures\TaggedTool;

function makeFilterContext(array $tools, array $allowedClasses = [], array $allowedTags = []): ServerContext
{
    return new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test', '1.0.0'),
        instructions: 'x',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: $tools,
        resources: [],
        prompts: [],
        allowedToolClasses: $allowedClasses,
        allowedToolTags: $allowedTags,
    );
}

it('returns all tools when no filter is set', function (): void {
    $context = makeFilterContext([TaggedTool::class, SayHiTool::class]);

    $names = $context->tools()->map(fn ($t) => $t->name())->all();

    expect($names)->toContain('tagged-tool')->toContain('say-hi-tool');
    expect($context->tools())->toHaveCount(2);
});

it('returns only the matched class when filtering by tool class', function (): void {
    $context = makeFilterContext(
        [TaggedTool::class, SayHiTool::class],
        allowedClasses: [TaggedTool::class],
    );

    $names = $context->tools()->map(fn ($t) => $t->name())->all();

    expect($names)->toContain('tagged-tool')
        ->not->toContain('say-hi-tool');
    expect($context->tools())->toHaveCount(1);
});

it('returns only tagged tools when filtering by tag', function (): void {
    $context = makeFilterContext(
        [TaggedTool::class, SayHiTool::class],
        allowedTags: ['weather'],
    );

    $names = $context->tools()->map(fn ($t) => $t->name())->all();

    expect($names)->toContain('tagged-tool')
        ->not->toContain('say-hi-tool');
    expect($context->tools())->toHaveCount(1);
});

it('uses union semantics when both class and tag filters are given', function (): void {
    $context = makeFilterContext(
        [TaggedTool::class, SayHiTool::class],
        allowedClasses: [SayHiTool::class],
        allowedTags: ['weather'],
    );

    $names = $context->tools()->map(fn ($t) => $t->name())->all();

    expect($names)->toContain('tagged-tool')->toContain('say-hi-tool');
    expect($context->tools())->toHaveCount(2);
});

it('returns an empty collection when no tools match the filter', function (): void {
    $context = makeFilterContext(
        [TaggedTool::class, SayHiTool::class],
        allowedTags: ['nonexistent-tag'],
    );

    expect($context->tools())->toHaveCount(0);
});

it('does not filter when both filter arrays are empty', function (): void {
    $context = makeFilterContext(
        [TaggedTool::class, SayHiTool::class],
        allowedClasses: [],
        allowedTags: [],
    );

    expect($context->tools())->toHaveCount(2);
});
