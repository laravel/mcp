<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Closure;

class SecurityScheme
{
    protected string $type;

    /** @var array<int, string> */
    protected array $scopes = [];

    /** @var array<string, mixed> */
    protected array $additionalData = [];

    private function __construct(string $type = '')
    {
        if ($type !== '') {
            $this->type = $type;
        }
    }

    protected function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param  (Closure(SecurityScheme): array<string, SecurityScheme|array<string, mixed>>)|array<string, SecurityScheme|array<string, mixed>>  $schemes
     * @return array<string, array<string, mixed>>
     */
    public static function make(Closure|array $schemes = []): array
    {
        if ($schemes instanceof Closure) {
            $schemes = $schemes(new self);
        }

        $result = collect($schemes)->map(
            fn ($scheme) => $scheme instanceof self ? $scheme->toArray() : $scheme
        );

        return $result->toArray();
    }

    /**
     * @param  string|array<int, string>  ...$scopes
     */
    public function scopes(string|array ...$scopes): self
    {
        $this->scopes = collect($scopes)
            ->flatten()
            ->toArray();

        return $this;
    }

    public function with(string $key, mixed $value): self
    {
        $this->additionalData[$key] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $scheme = array_merge(['type' => $this->type], $this->additionalData);

        if ($this->scopes !== []) {
            $scheme['scopes'] = $this->scopes;
        }

        return $scheme;
    }

    public static function type(string $type): self
    {
        return new self($type);
    }

    /**
     * @return array<string, string>
     */
    public static function noauth(): array
    {
        return ['type' => 'noauth'];
    }

    /**
     * @param  string|array<int, string>  ...$scopes
     */
    public static function oauth2(string|array ...$scopes): self
    {
        $instance = self::type('oauth2');

        if ($scopes !== []) {
            $instance->scopes(...$scopes);
        }

        return $instance;
    }

    /**
     * @return array<string, string>
     */
    public static function apiKey(string $name = 'api_key', string $in = 'header'): array
    {
        return [
            'type' => 'apiKey',
            'name' => $name,
            'in' => $in,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(string $format = 'JWT'): array
    {
        return [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => $format,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function basic(): array
    {
        return [
            'type' => 'http',
            'scheme' => 'basic',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return $this->toArray();
    }
}
