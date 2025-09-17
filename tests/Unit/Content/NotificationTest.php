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
