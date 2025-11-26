<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\SupportsURITemplate;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Resource;
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

it('reads resource template by matching a URI pattern', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}');
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

it('returns the actual requested URI in response, not the template pattern', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}');
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

    expect($payload['result']['contents'][0]['uri'])->toBe($requestedUri)
        ->and($payload['result']['contents'][0]['uri'])->not->toBe('file://users/{userId}');
});

it('extracts variables from URI template and passes to handler', function (string $templatePattern, string $uri, array $expected): void {
    $resource = new class($templatePattern) extends Resource implements SupportsURITemplate
    {
        public function __construct(private string $pattern) {}

        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make($this->pattern);
        }

        public function handle(Request $request): Response
        {
            return Response::json($request->all());
        }
    };

    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $uri]
    );

    $readResource = new ReadResource;
    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray();

    $responseData = json_decode((string) $payload['result']['contents'][0]['text'], true);

    expect($responseData)->toBe($expected);
})->with([
    'single variable' => [
        'templatePattern' => 'file://users/{userId}',
        'uri' => 'file://users/42',
        'expected' => ['userId' => '42'],
    ],
    'multiple variables' => [
        'templatePattern' => 'file://users/{userId}/files/{fileId}',
        'uri' => 'file://users/100/files/document.pdf',
        'expected' => ['userId' => '100', 'fileId' => 'document.pdf'],
    ],
]);

it('preserves sessionId and meta from the original request for template resources', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::json([
                'sessionId' => $request->sessionId(),
                'meta' => $request->meta(),
                'arguments' => $request->all(),
            ]);
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
        $result = $readResource->handle($jsonRpcRequest, $context);
        $payload = $result->toArray();

        $responseData = json_decode((string) $payload['result']['contents'][0]['text'], true);

        expect($responseData['sessionId'])->toBe($sessionId)
            ->and($responseData['meta'])->toBe($meta)
            ->and($responseData['arguments'])->toHaveKey('userId', '42')
            ->and($responseData['arguments'])->toHaveKey('format', 'json');
    } finally {
        $container->forgetInstance('mcp.request');
    }
});

it('template handler receives variables via request get method', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://posts/{postId}/comments/{commentId}');
        }

        public function handle(Request $request): Response
        {
            return Response::json([
                'postId' => $request->get('postId'),
                'commentId' => $request->get('commentId'),
            ]);
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
    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray();

    $responseData = json_decode((string) $payload['result']['contents'][0]['text'], true);

    expect($responseData['postId'])->toBe('42')
        ->and($responseData['commentId'])->toBe('7');
});

it('tries static resources before template matching', function (): void {
    $staticResource = $this->makeResource('Static resource content');

    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://resources/{resourceId}');
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

it('returns the first matching template when multiple templates exist', function (): void {
    $template1 = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('First template');
        }
    };

    $template2 = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{id}');
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

    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}');
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
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}/posts/{postId}');
        }

        public function handle(Request $request): Response
        {
            return Response::json($request->all());
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
    $firstResult = $readResource->handle($firstJsonRpcRequest, $context);
    $firstPayload = $firstResult->toArray();
    $firstRequestVars = json_decode((string) $firstPayload['result']['contents'][0]['text'], true);

    $secondJsonRpcRequest = new JsonRpcRequest(
        id: 2,
        method: 'resources/read',
        params: ['uri' => 'file://users/200/posts/99']
    );
    $secondResult = $readResource->handle($secondJsonRpcRequest, $context);
    $secondPayload = $secondResult->toArray();
    $secondRequestVars = json_decode((string) $secondPayload['result']['contents'][0]['text'], true);

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

it('sets uri on request when reading resource templates', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}');
        }

        public function handle(Request $request): Response
        {
            return Response::json(['uri' => $request->uri()]);
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

    $responseData = json_decode((string) $payload['result']['contents'][0]['text'], true);

    expect($responseData['uri'])->toBe($requestedUri);
});

it('provides both uri and extracted variables in request for templates', function (): void {
    $template = new class extends Resource implements SupportsURITemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return UriTemplate::make('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::json([
                'uri' => $request->uri(),
                'userId' => $request->get('userId'),
                'fileId' => $request->get('fileId'),
            ]);
        }
    };

    $context = $this->getServerContext([
        'resources' => [$template],
    ]);

    $requestedUri = 'file://users/123/files/document.pdf';
    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $requestedUri]
    );

    $readResource = new ReadResource;
    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray();

    $responseData = json_decode((string) $payload['result']['contents'][0]['text'], true);

    expect($responseData)->toBe([
        'uri' => 'file://users/123/files/document.pdf',
        'userId' => '123',
        'fileId' => 'document.pdf',
    ]);
});

it('uri is correctly set and isolated for consecutive requests', function (string $resourceType, string $firstUri, string $secondUri): void {
    if ($resourceType === 'template') {
        $resource = new class extends Resource implements SupportsURITemplate
        {
            public function uriTemplate(): UriTemplate
            {
                return UriTemplate::make('file://users/{userId}');
            }

            public function handle(Request $request): Response
            {
                return Response::json(['uri' => $request->uri()]);
            }
        };
    } else {
        $resource = new class extends Resource
        {
            protected string $uri = 'file://static/resource';

            protected string $mimeType = 'text/plain';

            public function handle(Request $request): Response
            {
                return Response::json(['uri' => $request->uri()]);
            }
        };
    }

    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $readResource = new ReadResource;

    $firstRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $firstUri]
    );
    $firstResult = $readResource->handle($firstRequest, $context);
    $firstPayload = $firstResult->toArray();
    $firstResponseData = json_decode((string) $firstPayload['result']['contents'][0]['text'], true);

    $secondRequest = new JsonRpcRequest(
        id: 2,
        method: 'resources/read',
        params: ['uri' => $secondUri]
    );
    $secondResult = $readResource->handle($secondRequest, $context);
    $secondPayload = $secondResult->toArray();
    $secondResponseData = json_decode((string) $secondPayload['result']['contents'][0]['text'], true);

    expect($firstResponseData['uri'])->toBe($firstUri)
        ->and($secondResponseData['uri'])->toBe($secondUri);
})->with([
    'template resources' => [
        'resourceType' => 'template',
        'firstUri' => 'file://users/100',
        'secondUri' => 'file://users/200',
    ],
    'static resources' => [
        'resourceType' => 'static',
        'firstUri' => 'file://static/resource',
        'secondUri' => 'file://static/resource',
    ],
]);
