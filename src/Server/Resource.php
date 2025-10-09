<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Resources\Matching\SchemeValidator;
use Laravel\Mcp\Server\Resources\Matching\UriValidator;
use Laravel\Mcp\Server\Resources\Matching\ValidatorInterface;
use Laravel\Mcp\Server\Resources\Uri;

abstract class Resource extends Primitive
{
    protected string $uri = '';

    private string $actualUri = '';

    protected string $mimeType = '';

    /**
     * We can use this property to make the method
     * $this->isTemplate() more efficient and
     * avoid compiling the regex of the uri
     */
    protected ?bool $isTemplate = null;

    /**
     * @var array<int, ValidatorInterface>
     */
    public static array $validators;

    /**
     * @return array<int, ValidatorInterface>
     */
    public static function getValidators(): array
    {
        // To match the route, we will use a chain of responsibility pattern with the
        // validator implementations. We will spin through each one making sure it
        // passes, and then we will know if the route as a whole matches request.
        return static::$validators ?? static::$validators = [
            new UriValidator, new SchemeValidator,
        ];
    }

    public function uri(): string
    {
        return
            $this->actualUri !== ''
                ? $this->actualUri
                : $this->templateUri();
    }

    public function templateUri(): string
    {
        return $this->uri !== ''
            ? $this->uri
            : 'file://resources/'.Str::kebab(class_basename($this));
    }

    public function mimeType(): string
    {
        return $this->mimeType !== ''
            ? $this->mimeType
            : 'text/plain';
    }

    public function setActualUri(string $uri): static
    {
        $this->actualUri = $uri;

        return $this;
    }

    public function match(string $uri): bool
    {
        if ($this->uri() === $uri) {
            return true;
        }

        foreach (self::getValidators() as $validator) {
            if (! $validator->matches($this, $uri)) {
                return false;
            }
        }

        return true;
    }

    public function isTemplate(): bool
    {
        return $this->isTemplate ?? count(Uri::pathRegex($this->uri())['variables']) > 0;
    }

    public function getUriPath(): string
    {
        return Uri::path($this->uri());
    }

    public function getUriScheme(): string
    {
        return Uri::scheme($this->uri());
    }

    /**
     * @return array<string, mixed>
     */
    public function toMethodCall(): array
    {
        $response = ['uri' => $this->uri()];

        if ($this->isTemplate()) {
            $response['uriTemplate'] = $this->templateUri();
        }

        return $response;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'mimeType' => $this->mimeType(),
            ...$this->toMethodCall(),
        ];
    }
}
