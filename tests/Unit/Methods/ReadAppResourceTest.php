<?php

declare(strict_types=1);

use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Transport\JsonRpcRequest;

it('includes _meta.ui on content items for ui resources', function (): void {
    config(['app.url' => 'https://myapp.example.com']);

    $resource = new class extends AppResource
    {
        public function appMeta(): AppMeta
        {
            return AppMeta::make()
                ->csp(Csp::make()->connectDomains(['https://api.example.com']))
                ->prefersBorder()
                ->domain('sandbox.example.com');
        }

        public function handle(Request $request): Response
        {
            return Response::text('<html><body>Hello</body></html>');
        }
    };

    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $resource->uri()]
    );

    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray()['result'];

    expect($payload['contents'][0])->toHaveKey('_meta')
        ->and($payload['contents'][0]['_meta']['ui'])->toEqual([
            'csp' => ['connectDomains' => ['https://api.example.com']],
            'prefersBorder' => true,
            'domain' => 'sandbox.example.com',
        ]);
});

it('includes auto-resolved Claude domain in _meta.ui content when no appMeta set', function (): void {
    $currentUrl = 'https://myapp.example.com/mcp';
    $previousRequest = app('request');

    try {
        app()->instance('request', HttpRequest::create($currentUrl, 'GET'));

        $expectedDomain = str(hash('sha256', $currentUrl))
            ->limit(32, '')
            ->append(AppResource::CLAUDE_DOMAIN_SUFFIX)
            ->value();

        $resource = new class extends AppResource
        {
            public function handle(Request $request): Response
            {
                return Response::text('<html><body>Hello</body></html>');
            }
        };

        $readResource = new ReadResource;
        $context = $this->getServerContext([
            'resources' => [$resource],
        ]);

        $jsonRpcRequest = new JsonRpcRequest(
            id: 1,
            method: 'resources/read',
            params: ['uri' => $resource->uri()]
        );

        $result = $readResource->handle($jsonRpcRequest, $context);
        $payload = $result->toArray()['result'];

        expect($payload['contents'][0])->toHaveKey('_meta')
            ->and($payload['contents'][0]['_meta']['ui'])->toEqual([
                'domain' => $expectedDomain,
                'prefersBorder' => true,
            ]);
    } finally {
        app()->instance('request', $previousRequest);
    }
});

it('does not include _meta.ui on content items for regular resources', function (): void {
    $resource = $this->makeResource('regular content');

    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $resource->uri()]
    );

    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray()['result'];

    expect($payload['contents'][0])->not->toHaveKey('_meta');
});

it('excludes _meta.ui.domain from resources/read and resources/list when app meta is without a domain', function (): void {
    $resource = new class extends AppResource
    {
        public function appMeta(): AppMeta
        {
            return AppMeta::make()->prefersBorder()->withoutDomain();
        }

        public function handle(Request $request): Response
        {
            return Response::text('<html><body>Hello</body></html>');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $read = (new ReadResource)->handle(new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $resource->uri()]
    ), $context)->toArray()['result'];

    $list = (new ListResources)->handle(new JsonRpcRequest(
        id: 2,
        method: 'resources/list',
        params: []
    ), $context)->toArray()['result'];

    expect($read['contents'][0]['_meta']['ui'])->toBe(['prefersBorder' => true])
        ->and($list['resources'][0]['_meta']['ui'])->toBe(['prefersBorder' => true]);
});
