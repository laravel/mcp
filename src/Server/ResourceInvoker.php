<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use Traversable;

class ResourceInvoker
{
    use InteractsWithResponses;

    /**
     * @throws BindingResolutionException
     */
    public function invoke(Resource $resource, string $uri): mixed
    {
        $container = Container::getInstance();

        $request = $container->make(Request::class);
        $request->setUri($uri);

        if ($resource instanceof HasUriTemplate) {
            $variables = $resource->uriTemplate()->match($uri) ?? [];
            $request->merge($variables);
        }

        $container->instance(Request::class, $request);

        if ($resource instanceof AppResource) {
            $container->instance('mcp.library_scripts', $resource->libraryScripts());
        }

        try {
            // @phpstan-ignore-next-line
            return $container->call([$resource, 'handle']);
        } finally {
            $container->forgetInstance(Request::class);
            $container->forgetInstance('mcp.library_scripts');
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function read(Resource $resource, string $uri): Response
    {
        $container = Container::getInstance();

        $hadParentRequest = $container->bound('mcp.request');
        $parentRequest = $hadParentRequest ? $container->make('mcp.request') : null;

        $container->instance('mcp.request', new Request(meta: $parentRequest instanceof Request ? $parentRequest->meta() : null));

        try {
            $result = $this->invoke($resource, $uri);

            if ($result instanceof Traversable) {
                $result = iterator_to_array($result, false);
            }

            $responses = $this->toResponseFactory($result)->responses()
                ->reject(fn (Response $response): bool => $response->isNotification())
                ->values();
        } finally {
            if ($hadParentRequest) {
                $container->instance('mcp.request', $parentRequest);
            } else {
                $container->forgetInstance('mcp.request');
            }
        }

        $error = $responses->first(fn (Response $response): bool => $response->isError());

        if ($error instanceof Response) {
            return $error;
        }

        if ($responses->count() !== 1) {
            throw new InvalidArgumentException("Resource [{$uri}] must return exactly one content item to be embedded, but returned {$responses->count()}.");
        }

        return $responses->sole();
    }

    /**
     * @return array<string, mixed>
     */
    public function contents(Resource $resource, string $uri, Response $response): array
    {
        $content = [
            ...$response->content()->toResource($resource),
            'uri' => $uri,
        ];

        $appMeta = $resource instanceof AppResource ? $resource->resolvedAppMeta() : null;

        if ($appMeta !== null && $appMeta !== []) {
            $content['_meta'] = array_merge($content['_meta'] ?? [], [
                'ui' => $appMeta,
            ]);
        }

        return $content;
    }
}
