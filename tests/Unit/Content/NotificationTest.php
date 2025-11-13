<?php

use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('may be used in tools', function (): void {
    $notification = new Notification('booking/starting', ['step' => 1]);

    $payload = $notification->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'method' => 'booking/starting',
        'params' => ['step' => 1],
    ]);
});

it('may be used in prompts', function (): void {
    $notification = new Notification('booking/completed', ['step' => 2]);

    $payload = $notification->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'method' => 'booking/completed',
        'params' => ['step' => 2],
    ]);
});

it('may be used in resources without adding metadata', function (): void {
    $notification = new Notification('status/update', ['ok' => true]);
    $resource = new class extends Resource
    {
        protected string $uri = 'file://ignored.txt';

        protected string $name = 'ignored';

        protected string $title = 'Ignored';

        protected string $mimeType = 'text/plain';
    };

    $payload = $notification->toResource($resource);

    expect($payload)->toEqual([
        'method' => 'status/update',
        'params' => ['ok' => true],
    ]);
});

it('casts to string as method', function (): void {
    $notification = new Notification('booking/starting', []);

    expect((string) $notification)->toBe('booking/starting');
});

it('converts to array with method and params', function (): void {
    $notification = new Notification('notify/me', ['x' => 1, 'y' => 2]);

    expect($notification->toArray())->toEqual([
        'method' => 'notify/me',
        'params' => ['x' => 1, 'y' => 2],
    ]);
});

it('supports _meta via setMeta', function (): void {
    $notification = new Notification('test/event', ['data' => 'value']);
    $notification->setMeta(['author' => 'system']);

    expect($notification->toArray())->toEqual([
        'method' => 'test/event',
        'params' => [
            'data' => 'value',
            '_meta' => ['author' => 'system'],
        ],
    ]);
});

it('supports _meta in params', function (): void {
    $notification = new Notification('test/event', [
        'data' => 'value',
        '_meta' => ['source' => 'params'],
    ]);

    expect($notification->toArray())->toEqual([
        'method' => 'test/event',
        'params' => [
            'data' => 'value',
            '_meta' => ['source' => 'params'],
        ],
    ]);
});

it('allows both top-level _meta and params _meta independently', function (): void {
    $notification = new Notification('test/event', [
        'data' => 'value',
        '_meta' => ['source' => 'params', 'keep' => 'this'],
    ]);
    $notification->setMeta(['author' => 'system', 'level' => 'top']);

    expect($notification->toArray())->toEqual([
        'method' => 'test/event',
        'params' => [
            'data' => 'value',
            '_meta' => ['source' => 'params', 'keep' => 'this'],
        ],
    ]);
});

it('does not include _meta if null', function (): void {
    $notification = new Notification('test/event', ['data' => 'value']);

    expect($notification->toArray())->toEqual([
        'method' => 'test/event',
        'params' => ['data' => 'value'],
    ])
        ->and($notification->toArray())->not->toHaveKey('_meta');
});

it('does not include _meta if not set', function (): void {
    $notification = new Notification('test/event', ['data' => 'value']);

    expect($notification->toArray())->toEqual([
        'method' => 'test/event',
        'params' => ['data' => 'value'],
    ])
        ->and($notification->toArray())->not->toHaveKey('_meta');
});
