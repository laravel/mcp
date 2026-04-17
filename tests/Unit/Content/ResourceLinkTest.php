<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\ResourceLink;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools', function (): void {
    $rl = new ResourceLink('https://example.com/data.json', 'Dataset', 'application/json');

    $payload = $rl->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.json',
        'name' => 'Dataset',
        'mimeType' => 'application/json',
    ]);
});

it('may be used in prompts', function (): void {
    $rl = new ResourceLink('https://example.com/data.json', 'Dataset', 'application/json');

    $payload = $rl->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.json',
        'name' => 'Dataset',
        'mimeType' => 'application/json',
    ]);
});

it('may be used in resources', function (): void {
    $rl = new ResourceLink('https://example.com/data.json', 'Dataset', 'application/json');

    $resource = new class extends Resource
    {
        protected string $uri = 'file://store/items.json';

        protected string $mimeType = 'application/json';
    };

    $payload = $rl->toResource($resource);

    expect($payload)->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.json',
        'name' => 'Dataset',
        'mimeType' => 'application/json',
    ]);
});

it('casts to string as the URI', function (): void {
    $rl = new ResourceLink('https://example.com/resource');

    expect((string) $rl)->toBe('https://example.com/resource');
});

it('converts to array with required type and uri fields', function (): void {
    $rl = new ResourceLink('https://example.com/resource');

    expect($rl->toArray())->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
    ]);
});

it('omits optional fields when null', function (): void {
    $rl = new ResourceLink('https://example.com/resource');

    $array = $rl->toArray();

    expect($array)->not->toHaveKey('name')
        ->and($array)->not->toHaveKey('mimeType')
        ->and($array)->not->toHaveKey('description');
});

it('includes name when provided', function (): void {
    $rl = new ResourceLink('https://example.com/resource', 'My Resource');

    expect($rl->toArray())->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
        'name' => 'My Resource',
    ]);
});

it('includes mimeType when provided', function (): void {
    $rl = new ResourceLink('https://example.com/data.csv', null, 'text/csv');

    expect($rl->toArray())->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.csv',
        'mimeType' => 'text/csv',
    ]);
});

it('includes description when provided', function (): void {
    $rl = new ResourceLink('https://example.com/resource', null, null, 'A useful resource');

    expect($rl->toArray())->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
        'description' => 'A useful resource',
    ]);
});

it('includes all optional fields when all are provided', function (): void {
    $rl = new ResourceLink(
        'https://example.com/data.json',
        'Full Dataset',
        'application/json',
        'Complete dataset for analysis',
    );

    expect($rl->toArray())->toEqual([
        'type' => 'resource_link',
        'uri' => 'https://example.com/data.json',
        'name' => 'Full Dataset',
        'mimeType' => 'application/json',
        'description' => 'Complete dataset for analysis',
    ]);
});

it('supports meta via setMeta', function (): void {
    $rl = new ResourceLink('https://example.com/resource', 'Resource');
    $rl->setMeta(['version' => '2']);

    expect($rl->toArray())->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
        'name' => 'Resource',
        '_meta' => ['version' => '2'],
    ]);
});

it('does not include meta if null', function (): void {
    $rl = new ResourceLink('https://example.com/resource');

    $array = $rl->toArray();

    expect($array)->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'https://example.com/resource',
    ])->and($array)->not->toHaveKey('_meta');
});
