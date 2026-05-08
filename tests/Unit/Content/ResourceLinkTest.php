<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Content\ResourceLink;
use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

it('may be used in tools', function (): void {
    $link = new ResourceLink(
        'file:///tmp/report.csv',
        'report.csv',
        'text/csv',
        'Generated report'
    );

    $payload = $link->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'type' => 'resource_link',
        'uri' => 'file:///tmp/report.csv',
        'name' => 'report.csv',
        'description' => 'Generated report',
        'mimeType' => 'text/csv',
    ]);
});

it('may be used in prompts', function (): void {
    $link = new ResourceLink('file:///tmp/report.csv', 'report.csv');

    $payload = $link->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'type' => 'resource_link',
        'uri' => 'file:///tmp/report.csv',
        'name' => 'report.csv',
    ]);
});

it('supports annotations and meta', function (): void {
    $link = new #[Audience([Role::User])] class('file:///tmp/report.csv', 'report.csv') extends ResourceLink {};
    $link->setMeta(['origin' => 'export']);

    $payload = $link->toArray();

    expect($payload)->toMatchArray([
        'type' => 'resource_link',
        'uri' => 'file:///tmp/report.csv',
        'name' => 'report.csv',
        'annotations' => ['audience' => ['user']],
        '_meta' => ['origin' => 'export'],
    ]);
});
