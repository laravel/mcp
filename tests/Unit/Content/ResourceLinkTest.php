<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Server\Content\ResourceLink;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools and prompts', function (): void {
    $resourceLink = new ResourceLink(
        uri: 'https://example.com/data.json',
        name: 'Dataset',
        mimeType: 'application/json',
    );

    $expected = [
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.json',
        'name' => 'Dataset',
        'mimeType' => 'application/json',
    ];

    expect($resourceLink->toTool(new class extends Tool {}))->toEqual($expected)
        ->and($resourceLink->toPrompt(new class extends Prompt {}))->toEqual($expected);
});

it('may not be used in resources', function (): void {
    $resourceLink = new ResourceLink('https://example.com/data.json');

    $resource = new class extends Resource
    {
        protected string $uri = 'file://store/items.json';

        protected string $mimeType = 'application/json';
    };

    expect(fn (): array => $resourceLink->toResource($resource))
        ->toThrow(InvalidArgumentException::class, 'ResourceLink content may not be used in resources.');
});

it('casts to string as the URI', function (): void {
    $resourceLink = new ResourceLink('https://example.com/resource');

    expect((string) $resourceLink)->toBe('https://example.com/resource');
});

it('converts to array with required type and uri fields', function (): void {
    $resourceLink = new ResourceLink('https://example.com/resource');

    expect($resourceLink->toArray())->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
    ]);
});

it('omits optional fields when null', function (): void {
    $resourceLink = new ResourceLink('https://example.com/resource');

    $array = $resourceLink->toArray();

    expect($array)
        ->not->toHaveKey('name')
        ->not->toHaveKey('title')
        ->not->toHaveKey('description')
        ->not->toHaveKey('mimeType')
        ->not->toHaveKey('size')
        ->not->toHaveKey('annotations')
        ->not->toHaveKey('_meta');
});

it('includes all spec-defined fields when provided', function (): void {
    $resourceLink = new ResourceLink(
        uri: 'https://example.com/data.json',
        name: 'monthly-report',
        title: 'Monthly Sales Report',
        description: 'Sales rollup by region',
        mimeType: 'application/json',
        size: 2048,
    );

    expect($resourceLink->toArray())->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.json',
        'name' => 'monthly-report',
        'title' => 'Monthly Sales Report',
        'description' => 'Sales rollup by region',
        'mimeType' => 'application/json',
        'size' => 2048,
    ]);
});

it('includes annotations when set via fluent setters', function (): void {
    $resourceLink = (new ResourceLink('https://example.com/resource'))
        ->audience([Role::User, Role::Assistant])
        ->priority(0.9)
        ->lastModified('2026-05-07T12:00:00Z');

    expect($resourceLink->toArray())->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
        'annotations' => [
            'audience' => ['user', 'assistant'],
            'priority' => 0.9,
            'lastModified' => '2026-05-07T12:00:00Z',
        ],
    ]);
});

it('accepts a single Role for audience', function (): void {
    $resourceLink = (new ResourceLink('https://example.com/resource'))->audience(Role::User);

    expect($resourceLink->toArray()['annotations']['audience'])->toBe(['user']);
});

it('rejects invalid priority values', function (): void {
    expect(fn (): ResourceLink => (new ResourceLink('https://example.com/resource'))->priority(1.5))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects invalid lastModified timestamps', function (): void {
    expect(fn (): ResourceLink => (new ResourceLink('https://example.com/resource'))->lastModified('not-a-date'))
        ->toThrow(InvalidArgumentException::class);
});

it('supports meta via setMeta', function (): void {
    $resourceLink = new ResourceLink('https://example.com/resource', name: 'Resource');
    $resourceLink->setMeta(['version' => '2']);

    expect($resourceLink->toArray())->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
        'name' => 'Resource',
        '_meta' => ['version' => '2'],
    ]);
});
