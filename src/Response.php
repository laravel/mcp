<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use JsonException;
use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\EmbeddedResource;
use Laravel\Mcp\Server\Content\Image;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\ResourceLink;
use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use League\Flysystem\UnableToReadFile;

class Response
{
    use Conditionable;
    use Macroable;

    protected function __construct(
        protected Content $content,
        protected Role $role = Role::User,
        protected bool $isError = false,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function notification(string $method, array $params = []): static
    {
        return new static(new Notification($method, $params));
    }

    public static function text(string $text): static
    {
        return new static(new Text($text));
    }

    public static function html(string $path): static
    {
        $path = str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) ? $path : resource_path($path);

        if (! file_exists($path)) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        return static::text((string) file_get_contents($path));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public static function view(string $view, array $data = [], array $mergeData = []): static
    {
        return static::text(view($view, $data, $mergeData)->render());
    }

    /**
     * @internal
     *
     * @throws JsonException
     */
    public static function json(mixed $content): static
    {
        return static::text(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function blob(string $content): static
    {
        return new static(new Blob($content));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function structured(array $response): ResponseFactory
    {
        if ($response === []) {
            throw new InvalidArgumentException('Structured content cannot be empty.');
        }

        try {
            $json = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException("Invalid structured content: {$jsonException->getMessage()}", 0, $jsonException);
        }

        $content = Response::text($json);

        return (new ResponseFactory($content))->withStructuredContent($response);
    }

    public static function error(string $text): static
    {
        return new static(new Text($text), isError: true);
    }

    public function content(): Content
    {
        return $this->content;
    }

    /**
     * @param  Response|array<int, Response>  $responses
     */
    public static function make(Response|array $responses): ResponseFactory
    {
        return new ResponseFactory($responses);
    }

    /**
     * @param  array<string, mixed>|string  $meta
     */
    public function withMeta(array|string $meta, mixed $value = null): static
    {
        $this->content->setMeta($meta, $value);

        return $this;
    }

    public static function audio(string $data, string $mimeType = 'audio/wav'): static
    {
        return new static(new Audio($data, $mimeType));
    }

    public static function image(string $data, string $mimeType = 'image/png'): static
    {
        return new static(new Image($data, $mimeType));
    }

    /**
     * @param  string|class-string<Resource>|Resource|ResourceLink  $uri
     * @param  array<string, mixed>  $annotations
     * @param  list<Icon>  $icons
     */
    public static function resourceLink(
        string|Resource|ResourceLink $uri,
        ?string $name = null,
        ?string $mimeType = null,
        ?string $title = null,
        ?string $description = null,
        ?int $size = null,
        array $annotations = [],
        array $icons = [],
    ): static {
        if (is_string($uri) && is_subclass_of($uri, Resource::class)) {
            $uri = Container::getInstance()->make($uri);
        }

        $link = match (true) {
            $uri instanceof ResourceLink => $uri,
            $uri instanceof Resource => (new ResourceLink(
                uri: $uri->uri(),
                name: $name ?? $uri->name(),
                mimeType: $mimeType ?? $uri->mimeType(),
                title: $title ?? $uri->title(),
                description: $description ?? $uri->description(),
                size: $size,
                annotations: array_merge($uri->annotations(), $annotations),
                icons: $icons === [] ? $uri->resolvedIcons() : $icons,
            )),
            default => new ResourceLink(
                uri: $uri,
                name: $name ?? throw new InvalidArgumentException('Resource link name is required when using a URI string.'),
                mimeType: $mimeType,
                title: $title,
                description: $description,
                size: $size,
                annotations: $annotations,
                icons: $icons,
            ),
        };

        return new static($link);
    }

    /**
     * @param  class-string<Resource>|Resource|EmbeddedResource  $resource
     * @param  array<string, mixed>  $arguments
     */
    public static function embeddedResource(string|Resource|EmbeddedResource $resource, array $arguments = []): static
    {
        if ($resource instanceof EmbeddedResource) {
            return new static($resource);
        }

        if (is_string($resource)) {
            if (! is_subclass_of($resource, Resource::class)) {
                throw new InvalidArgumentException('Embedded resource class must extend [Laravel\Mcp\Server\Resource].');
            }

            $resource = Container::getInstance()->make($resource);
        }

        $uri = $resource instanceof HasUriTemplate
            ? $resource->uriTemplate()->expand($arguments)
            : $resource->uri();

        return new static(new EmbeddedResource(
            static::resourceContent($resource, $uri, $arguments),
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected static function resourceContent(Resource $resource, string $uri, array $arguments): array
    {
        $container = Container::getInstance();
        $request = new Request($arguments, uri: $uri);

        if ($resource instanceof HasUriTemplate) {
            $request->merge($resource->uriTemplate()->match($uri) ?? []);
        }

        $container->instance(Request::class, $request);

        if ($resource instanceof AppResource) {
            $container->instance('mcp.library_scripts', $resource->libraryScripts());
        }

        try {
            $handler = [$resource, 'handle'];

            if (! is_callable($handler)) {
                throw new InvalidArgumentException('Embedded resource must define a callable handle method.');
            }

            $response = $container->call($handler);
        } finally {
            $container->forgetInstance(Request::class);
            $container->forgetInstance('mcp.library_scripts');
        }

        $content = match (true) {
            $response instanceof ResponseFactory => $response,
            $response instanceof Response => new ResponseFactory($response),
            is_string($response) => new ResponseFactory(str_contains($response, "\0") ? static::blob($response) : static::text($response)),
            is_array($response) => new ResponseFactory($response),
            default => throw new InvalidArgumentException('Embedded resources must return a Response instance, ResponseFactory, array of Response instances, or string.'),
        };

        if ($content->responses()->count() !== 1) {
            throw new InvalidArgumentException('Embedded resources must contain exactly one response.');
        }

        $resourceContent = $content->responses()->sole()->content()->toResource($resource);
        $resourceContent['uri'] = $uri;

        if ($resource instanceof AppResource) {
            $appMeta = $resource->resolvedAppMeta();

            if ($appMeta !== []) {
                $meta = $resourceContent['_meta'] ?? [];

                $resourceContent['_meta'] = array_merge(is_array($meta) ? $meta : [], [
                    'ui' => $appMeta,
                ]);
            }
        }

        return $resourceContent;
    }

    public static function fromStorage(string $path, ?string $disk = null, ?string $mimeType = null): static
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        try {
            $data = $storage->get($path);
        } catch (UnableToReadFile $unableToReadFile) {
            throw new InvalidArgumentException("File not found at path [{$path}].", 0, $unableToReadFile);
        }

        if ($data === null) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        $mimeType ??= $storage->mimeType($path) ?: throw new InvalidArgumentException(
            "Unable to determine MIME type for [{$path}].",
        );

        return match (true) {
            str_starts_with($mimeType, 'image/') => static::image($data, $mimeType),
            str_starts_with($mimeType, 'audio/') => static::audio($data, $mimeType),
            default => throw new InvalidArgumentException("Unsupported MIME type [{$mimeType}] for [{$path}]."),
        };
    }

    public function asAssistant(): static
    {
        return new static($this->content, Role::Assistant, $this->isError);
    }

    public function isNotification(): bool
    {
        return $this->content instanceof Notification;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
