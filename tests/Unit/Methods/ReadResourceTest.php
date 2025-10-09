<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

it('returns a valid resource result', function (): void {
    $resource = $this->makeResource('resource-content');
    $readResource = new ReadResource;
    $context = $this->getServerContext();
    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/read', params: ['uri' => $resource->uri()]);
    $resourceResult = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            [
                'text' => 'resource-content',
            ],
        ],
    ], $resourceResult);
});
it('returns a valid resource result for blob resources', function (): void {
    $resource = $this->makeBinaryResource(__DIR__.'/../../Fixtures/binary.png');
    $readResource = new ReadResource;
    $context = $this->getServerContext();
    $context = $this->getServerContext([
        'resources' => [
            $resource,
        ],
    ]);
    $jsonRpcRequest = new JsonRpcRequest(id: 1, method: 'resources/read', params: ['uri' => $resource->uri()]);
    $resourceResult = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            [
                'blob' => base64_encode(file_get_contents(__DIR__.'/../../Fixtures/binary.png')),
            ],
        ],
    ], $resourceResult);
});

it('throws error when uri is missing', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Missing [uri] parameter.');

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: [] // intentionally omitting 'uri'
    );

    $response = $readResource->handle($jsonRpcRequest, $context);

});

it('throws exception when resource is not found', function (): void {
    $this->expectException(JsonRpcException::class);

    $readResource = new ReadResource;
    $context = $this->getServerContext();

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/non-existent']
    );

    $readResource->handle($jsonRpcRequest, $context);
});

it('reads resource from template with single variable', function (): void {
    $template = $this->makeResourceTemplate('file://resources/user/{id}', 'User resource template');
    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resourceTemplates' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/user/123']
    );

    $result = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            [
                'text' => 'template-content',
            ],
        ],
    ], $result);
});

it('reads resource from template with multiple variables', function (): void {
    $template = $this->makeResourceTemplate('file://projects/{projectId}/files/{filename}');
    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resourceTemplates' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://projects/my-project/files/readme.md']
    );

    $result = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            [
                'text' => 'template-content',
            ],
        ],
    ], $result);
});

it('prefers exact static resource match over template match', function (): void {
    $staticResource = $this->makeResource('static-content', overrides: ['uri' => 'file://resources/user/123']);
    $template = $this->makeResourceTemplate('file://resources/user/{id}');

    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resources' => [$staticResource],
        'resourceTemplates' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/user/123']
    );

    $result = $readResource->handle($jsonRpcRequest, $context);

    // Should use static resource, not template
    $this->assertPartialMethodResult([
        'contents' => [
            [
                'text' => 'static-content',
            ],
        ],
    ], $result);
});

it('throws exception when neither static resource nor template matches', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Resource [file://resources/post/123] not found.');

    $template = $this->makeResourceTemplate('file://resources/user/{id}');
    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resourceTemplates' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/post/123']
    );

    $readResource->handle($jsonRpcRequest, $context);
});

it('injects extracted variables into request for template handle method', function (): void {
    // Create a template that actually USES the injected variable
    $template = new class extends \Laravel\Mcp\Server\ResourceTemplate
    {
        protected string $uriTemplate = 'file://resources/user/{userId}/post/{postId}';

        public function handle(\Laravel\Mcp\Request $request): \Laravel\Mcp\Response
        {
            // Access the injected variables
            $userId = $request->get('userId');
            $postId = $request->get('postId');

            return \Laravel\Mcp\Response::text("User: {$userId}, Post: {$postId}");
        }
    };

    $readResource = new ReadResource;
    $context = $this->getServerContext([
        'resourceTemplates' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://resources/user/123/post/456']
    );

    $result = $readResource->handle($jsonRpcRequest, $context);

    // Verify the variables were injected and used
    $this->assertPartialMethodResult([
        'contents' => [
            [
                'text' => 'User: 123, Post: 456',
                'uri' => 'file://resources/user/123/post/456',
            ],
        ],
    ], $result);
});
