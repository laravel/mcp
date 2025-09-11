<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Laravel\Mcp\Exceptions\NotImplementedException;
use Laravel\Mcp\Server\Content\ErrorTextContent;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\TextContent;
use Laravel\Mcp\Server\Contracts\Content;

class Response
{
    protected function __construct(protected Content $content)
    {
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
        return new static(new TextContent($text));
    }

    public static function error(string $text): static
    {
        return new static(new ErrorTextContent($text));
    }

    public function content(): Content
    {
        return $this->content;
    }

    /**
     * @throws NotImplementedException
     */
    public function audio(): Content
    {
        throw NotImplementedException::forMethod(static::class, __METHOD__);
    }

    /**
     * @throws NotImplementedException
     */
    public function image(): Content
    {
        throw NotImplementedException::forMethod(static::class, __METHOD__);
    }

    public function isNotification(): bool
    {
        return $this->content instanceof Notification;
    }

    public function isError(): bool
    {
        return $this->content instanceof ErrorTextContent;
    }
}
