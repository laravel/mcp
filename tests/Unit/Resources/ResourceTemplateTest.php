<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ResourceTemplate;
use Laravel\Mcp\Support\UriTemplate;

it('compiles URI template and extracts variable names', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    expect($resource->uri())->toBe('file://users/{userId}/files/{fileId}')
        ->and($resource->uriTemplate()->getVariableNames())->toBe(['userId', 'fileId'])
        ->and($resource)->toBeInstanceOf(ResourceTemplate::class);
});

it('matches URIs against a template pattern', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $matchingUri = 'file://users/123/files/document.txt';
    $nonMatchingUri = 'file://posts/123';

    expect($resource->uriTemplate()->match($matchingUri))->not->toBeNull()
        ->and($resource->uriTemplate()->match($nonMatchingUri))->toBeNull();
});

it('extracts variables from matching URI', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $uri = 'file://users/42/files/test.txt';
    $variables = $resource->uriTemplate()->match($uri);

    expect($variables)->toBe([
        'userId' => '42',
        'fileId' => 'test.txt',
    ]);
});

it('handles template resource with extracted variables', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            $userId = $request->get('userId');
            $fileId = $request->get('fileId');

            return Response::text("User: {$userId}, File: {$fileId}");
        }
    };

    $uri = 'file://users/100/files/data.json';
    $variables = $resource->uriTemplate()->match($uri);
    $request = new Request(['uri' => $uri, ...$variables]);

    $result = $resource->handle($request);
    $content = $result->content()->toResource($resource);

    expect($content['text'])->toBe('User: 100, File: data.json');
});

it('handles template with single variable', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://resource/{id}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    expect($resource->uriTemplate()->getVariableNames())->toBe(['id'])
        ->and($resource->uriTemplate()->match('file://resource/123'))->not->toBeNull()
        ->and($resource->uriTemplate()->match('file://resource/abc'))->toBe(['id' => 'abc']);
});

it('handles complex URI templates with multiple path segments', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://organizations/{orgId}/projects/{projectId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $uri = 'file://organizations/acme/projects/website/files/index.html';
    $variables = $resource->uriTemplate()->match($uri);

    expect($variables)->toBe([
        'orgId' => 'acme',
        'projectId' => 'website',
        'fileId' => 'index.html',
    ]);
});

it('does not match URIs with different path structure', function (): void {
    $resource = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://users/{userId}/files/{fileId}');
        }

        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    expect($resource->uriTemplate()->match('file://users/123'))->toBeNull()
        ->and($resource->uriTemplate()->match('file://users/123/files/abc/extra'))->toBeNull()
        ->and($resource->uriTemplate()->match('file://posts/123/files/abc'))->toBeNull();
});

it('static resources do not identify as templates', function (): void {
    $resource = new class extends Resource
    {
        protected string $uri = 'file://logs/app.log';

        public function handle(): Response
        {
            return Response::text('log content');
        }
    };

    expect($resource)->not->toBeInstanceOf(ResourceTemplate::class);
});

it('end to end template reads uri extracts variables and returns response', function (): void {
    $template = new class extends ResourceTemplate
    {
        public function uriTemplate(): UriTemplate
        {
            return new UriTemplate('file://api/{version}/users/{userId}/posts/{postId}');
        }

        public function handle(Request $request): Response
        {
            $version = $request->get('version');
            $userId = $request->get('userId');
            $postId = $request->get('postId');
            $uri = $request->get('uri');

            expect($version)->toBe('v2')
                ->and($userId)->toBe('alice')
                ->and($postId)->toBe('hello-world')
                ->and($uri)->toBe('file://api/v2/users/alice/posts/hello-world');

            return Response::text("API {$version}: User {$userId} - Post {$postId}");
        }
    };

    $uri = 'file://api/v2/users/alice/posts/hello-world';

    $extractedVars = $template->uriTemplate()->match($uri);

    expect($extractedVars)->toBe([
        'version' => 'v2',
        'userId' => 'alice',
        'postId' => 'hello-world',
    ]);

    $request = new Request(['uri' => $uri, ...$extractedVars]);

    $response = $template->handle($request);

    $content = $response->content()->toResource($template);

    expect($content['text'])->toBe('API v2: User alice - Post hello-world')
        ->and($template->uriTemplate()->getVariableNames())->toBe(['version', 'userId', 'postId'])
        ->and($template->uri())->toBe('file://api/{version}/users/{userId}/posts/{postId}');
});
