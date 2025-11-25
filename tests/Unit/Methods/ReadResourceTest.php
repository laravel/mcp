<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\ResourceTemplate;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Support\UriTemplate;
use Tests\Fixtures\ResourceWithResultMetaResource;

it('returns a valid resource result', function (): void {
    $resource = $this->makeResource('resource-content');
    $readResource = new ReadResource;
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

it('reads resource template by matching URI pattern', function (): void {
    $template = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('Template matched!');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://users/123']
    );

    $readResource = new ReadResource;
    $result = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            ['text' => 'Template matched!'],
        ],
    ], $result);
});

it('returns actual requested URI in response, not the template pattern', function (): void {
    $template = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('User data');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $requestedUri = 'file://users/42';
    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $requestedUri]
    );

    $readResource = new ReadResource;
    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray();

    // The response URI should be the actual requested URI, not the template pattern
    expect($payload['result']['contents'][0]['uri'])->toBe($requestedUri)
        ->and($payload['result']['contents'][0]['uri'])->not->toBe('file://users/{userId}');
});

it('extracts single variable from URI and passes to handler', function (): void {
    $capturedUserId = null;

    $template = new class($capturedUserId) extends ResourceTemplate
    {
        public function __construct(private &$capturedValue) {}

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            $this->capturedValue = $request->get('userId');

            return Response::text("User ID: {$this->capturedValue}");
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://users/42']
    );

    $readResource = new ReadResource;
    $readResource->handle($jsonRpcRequest, $context);

    expect($capturedUserId)->toBe('42');
});

it('extracts multiple variables from URI and passes to handler', function (): void {
    $capturedVars = null;

    $template = new class($capturedVars) extends ResourceTemplate
    {
        public function __construct(private &$capturedValues) {}

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            $this->capturedValues = [
                'userId' => $request->get('userId'),
                'fileId' => $request->get('fileId'),
            ];

            return Response::text('test');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://users/100/files/document.pdf']
    );

    $readResource = new ReadResource;
    $readResource->handle($jsonRpcRequest, $context);

    expect($capturedVars)->toBe([
        'userId' => '100',
        'fileId' => 'document.pdf',
    ]);
});

it('includes uri parameter along with extracted variables in request', function (): void {
    $capturedAll = null;

    $template = new class($capturedAll) extends ResourceTemplate
    {
        public function __construct(private &$capturedData) {}

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            $this->capturedData = $request->all();

            return Response::text('test');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $uri = 'file://users/789';
    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $uri]
    );

    $readResource = new ReadResource;
    $readResource->handle($jsonRpcRequest, $context);

    expect($capturedAll)->toBe([
        'userId' => '789',
    ]);
});

it('preserves sessionId and meta from the original request for template resources', function (): void {
    $capturedSessionId = null;
    $capturedMeta = null;
    $capturedArguments = null;

    $template = new class($capturedSessionId, $capturedMeta, $capturedArguments) extends ResourceTemplate
    {
        public function __construct(
            private &$sessionIdRef,
            private &$metaRef,
            private &$argumentsRef
        ) {}

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            $this->sessionIdRef = $request->sessionId();
            $this->metaRef = $request->meta();
            $this->argumentsRef = $request->all();

            return Response::text('test');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $sessionId = 'test-session-123';
    $meta = ['progressToken' => 'abc123'];
    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: [
            'uri' => 'file://users/42',
            'arguments' => ['format' => 'json'],
            '_meta' => $meta,
        ],
        sessionId: $sessionId
    );

    $container = Container::getInstance();
    $container->instance('mcp.request', $jsonRpcRequest->toRequest());

    try {
        $readResource = new ReadResource;
        $readResource->handle($jsonRpcRequest, $context);

        expect($capturedSessionId)->toBe($sessionId)
            ->and($capturedMeta)->toBe($meta)
            ->and($capturedArguments)->toHaveKey('userId', '42')
            ->and($capturedArguments)->toHaveKey('format', 'json');
    } finally {
        $container->forgetInstance('mcp.request');
    }
});

it('template handler receives variables via request get method', function (): void {
    $accessMethodWorks = false;

    $template = new class($accessMethodWorks) extends ResourceTemplate
    {
        public function __construct(private &$testResult) {}

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://posts/{postId}/comments/{commentId}');
        }

        public function handle(Request $request): Response
        {
            $postId = $request->get('postId');
            $commentId = $request->get('commentId');

            $this->testResult = ($postId === '42' && $commentId === '7');

            return Response::text('test');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://posts/42/comments/7']
    );

    $readResource = new ReadResource;
    $readResource->handle($jsonRpcRequest, $context);

    expect($accessMethodWorks)->toBeTrue();
});

it('tries static resources before template matching', function (): void {
    $staticResource = $this->makeResource('Static resource content');

    $template = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://resources/{resourceId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('Template matched!');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$staticResource, $template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $staticResource->uri()]
    );

    $readResource = new ReadResource;
    $result = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            ['text' => 'Static resource content'],
        ],
    ], $result);
});

it('returns first matching template when multiple templates exist', function (): void {
    $template1 = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('First template');
        }
    };

    $template2 = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{id}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('Second template');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template1, $template2],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://users/42']
    );

    $readResource = new ReadResource;
    $result = $readResource->handle($jsonRpcRequest, $context);

    $this->assertPartialMethodResult([
        'contents' => [
            ['text' => 'First template'],
        ],
    ], $result);
});

it('throws exception when URI does not match any template pattern', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Resource [file://posts/123] not found.');

    $template = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://posts/123']
    );

    $readResource = new ReadResource;
    $readResource->handle($jsonRpcRequest, $context);
});

it('returns a resource result with result-level meta when using ResponseFactory', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/read',
        'params' => [
            'uri' => 'file://resources/with-result-meta.txt',
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [ResourceWithResultMetaResource::class],
        prompts: [],
    );

    $method = new ReadResource;

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $payload = $response->toArray();

    expect($payload['id'])->toEqual(1)
        ->and($payload)->toMatchArray([
            'result' => [
                '_meta' => [
                    'last_modified' => '2025-01-01',
                    'version' => '1.0',
                ],
                'contents' => [
                    [
                        'text' => 'Resource content with result meta',
                        'uri' => 'file://resources/with-result-meta.txt',
                        'mimeType' => 'text/plain',
                    ],
                ],
            ],
        ]);
});

it('does not leak variables between consecutive template resource requests', function (): void {
    $firstRequestVars = null;
    $secondRequestVars = null;

    $template = new class($firstRequestVars, $secondRequestVars) extends ResourceTemplate
    {
        public function __construct(private &$firstRef, private &$secondRef) {}

        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/posts/{postId}');
        }

        public function handle(Request $request): Response
        {
            if ($this->firstRef === null) {
                $this->firstRef = $request->all();
            } else {
                $this->secondRef = $request->all();
            }

            return Response::text('test');
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $readResource = new ReadResource;

    $firstJsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => 'file://users/100/posts/42']
    );
    $readResource->handle($firstJsonRpcRequest, $context);

    $secondJsonRpcRequest = new JsonRpcRequest(
        id: 2,
        method: 'resources/read',
        params: ['uri' => 'file://users/200/posts/99']
    );
    $readResource->handle($secondJsonRpcRequest, $context);

    expect($firstRequestVars)->toBe([
        'userId' => '100',
        'postId' => '42',
    ])
        ->and($secondRequestVars)->toBe([
            'userId' => '200',
            'postId' => '99',
        ])
        ->and($secondRequestVars)->not->toHaveKey('userId', '100')
        ->and($secondRequestVars)->not->toHaveKey('postId', '42');
});
