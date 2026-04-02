<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta as AppMetaAttribute;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Server\Ui\Enums\Permission;
use Laravel\Mcp\Server\Ui\Permissions;

it('defaults to mcp-app mime type', function (): void {
    $resource = new class extends AppResource
    {
        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    expect($resource->mimeType())->toBe('text/html;profile=mcp-app');
});

it('defaults to ui:// uri scheme', function (): void {
    $resource = new class extends AppResource
    {
        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    expect($resource->uri())->toStartWith('ui://resources/');
});

it('includes _meta.ui in toArray output', function (): void {
    $resource = new class extends AppResource
    {
        public function appMeta(): AppMeta
        {
            return AppMeta::make()->prefersBorder();
        }

        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    $array = $resource->toArray();

    expect($array)->toHaveKey('_meta')
        ->and($array['_meta'])->toHaveKey('ui')
        ->and($array['_meta']['ui'])->toHaveKey('prefersBorder', true);
});

it('includes custom ui meta in toArray', function (): void {
    $resource = new class extends AppResource
    {
        public function appMeta(): AppMeta
        {
            return AppMeta::make()
                ->csp(Csp::make()->connectDomains(['https://api.example.com']))
                ->permissions(Permissions::make()->clipboardWrite())
                ->domain('sandbox.example.com')
                ->prefersBorder(false);
        }

        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    $array = $resource->toArray();

    expect($array['_meta']['ui'])->toEqual([
        'csp' => ['connectDomains' => ['https://api.example.com']],
        'permissions' => ['clipboardWrite' => (object) []],
        'domain' => 'sandbox.example.com',
        'prefersBorder' => false,
    ]);
});

it('allows custom uri override', function (): void {
    $resource = new class extends AppResource
    {
        protected string $uri = 'ui://custom/my-widget';

        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    expect($resource->uri())->toBe('ui://custom/my-widget');
});

it('serializes name title description and mimeType', function (): void {
    $resource = new class extends AppResource
    {
        protected string $description = 'A test UI resource';

        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    $array = $resource->toArray();

    expect($array)->toHaveKey('name')
        ->toHaveKey('title')
        ->toHaveKey('description', 'A test UI resource')
        ->toHaveKey('mimeType', 'text/html;profile=mcp-app')
        ->toHaveKey('uri');
});

it('includes domain from app url in _meta.ui by default', function (): void {
    config(['app.url' => 'https://myapp.example.com']);

    $resource = new class extends AppResource
    {
        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    $array = $resource->toArray();

    expect($array['_meta']['ui'])->toEqual(['domain' => 'myapp.example.com', 'prefersBorder' => true]);
});

it('attribute domain overrides auto-resolved domain', function (): void {
    config(['app.url' => 'https://myapp.example.com']);

    $resource = new #[AppMetaAttribute(domain: 'custom.example.com')] class extends AppResource
    {
        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    $array = $resource->toArray();

    expect($array['_meta']['ui']['domain'])->toBe('custom.example.com');
});

it('configures ui meta via attribute', function (): void {
    $resource = new #[AppMetaAttribute(connectDomains: ['https://api.example.com'], permissions: [Permission::Camera, Permission::ClipboardWrite], prefersBorder: true, domain: 'sandbox.example.com', )] class extends AppResource
    {
        public function handle(Request $request): Response
        {
            return Response::text('<html></html>');
        }
    };

    $array = $resource->toArray();

    expect($array['_meta']['ui'])->toEqual([
        'csp' => ['connectDomains' => ['https://api.example.com']],
        'permissions' => ['camera' => (object) [], 'clipboardWrite' => (object) []],
        'prefersBorder' => true,
        'domain' => 'sandbox.example.com',
    ]);
});
