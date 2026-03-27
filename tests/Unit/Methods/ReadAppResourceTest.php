<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\AppResource;

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

it('includes auto-resolved domain in _meta.ui content when no appMeta set', function (): void {
    config(['app.url' => 'https://myapp.example.com']);

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
            'domain' => 'myapp.example.com',
            'prefersBorder' => true,
        ]);
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
