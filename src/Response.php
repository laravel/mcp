<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use JsonException;
use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Image;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Contracts\Content;

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
     *
     * @throws JsonException
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

    public static function audioFromStorage(string $path, ?string $disk = null, ?string $mimeType = null): static
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        $data = $storage->get($path);

        if ($data === null) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        return static::audio(
            $data,
            $mimeType ?? $storage->mimeType($path) ?: throw new InvalidArgumentException(
                "Unable to determine MIME type for [{$path}].",
            ),
        );
    }

    public static function image(string $data, string $mimeType = 'image/png'): static
    {
        return new static(new Image($data, $mimeType));
    }

    public static function imageFromStorage(string $path, ?string $disk = null, ?string $mimeType = null): static
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        $data = $storage->get($path);

        if ($data === null) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        return static::image(
            $data,
            $mimeType ?? $storage->mimeType($path) ?: throw new InvalidArgumentException(
                "Unable to determine MIME type for [{$path}].",
            ),
        );
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
