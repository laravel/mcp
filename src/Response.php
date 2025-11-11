<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use JsonException;
use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Exceptions\NotImplementedException;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Contracts\Content;

class Response
{
    use Conditionable;
    use Macroable;

    /**
     * @param  array<string, mixed>|null  $meta
     */
    protected function __construct(
        protected Content $content,
        protected Role $role = Role::USER,
        protected bool $isError = false,
        protected ?array $meta = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>|null  $meta
     */
    public static function notification(string $method, array $params = [], ?array $meta = null): static
    {
        return new static(new Notification($method, $params, $meta));
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function text(string $text, ?array $meta = null): static
    {
        return new static(new Text($text, $meta));
    }

    /**
     * @internal
     *
     * @param  array<string, mixed>|null  $meta
     *
     * @throws JsonException
     */
    public static function json(mixed $content, ?array $meta = null): static
    {
        return static::text(json_encode(
            $content,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
        ), $meta);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function blob(string $content, ?array $meta = null): static
    {
        return new static(new Blob($content, $meta));
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
     * @throws NotImplementedException
     */
    public static function audio(): Content
    {
        throw NotImplementedException::forMethod(static::class, __METHOD__);
    }

    /**
     * @throws NotImplementedException
     */
    public static function image(): Content
    {
        throw NotImplementedException::forMethod(static::class, __METHOD__);
    }

    public function asAssistant(): static
    {
        return new static($this->content, Role::ASSISTANT, $this->isError, $this->meta);
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
