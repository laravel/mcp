<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Server\Concerns\HasMeta;

class ResponseFactory
{
    use Conditionable;
    use HasMeta;
    use Macroable;

    /**
     * @var Collection<int, Response>
     */
    protected Collection $responses;

    /**
     * @param  Response|array<int, Response>  $responses
     */
    protected function __construct(Response|array $responses)
    {
        $this->responses = collect(Arr::wrap($responses));
    }

    /**
     * @param  Response|array<int, Response>  $responses
     */
    public static function make(Response|array $responses): static
    {
        if (is_array($responses)) {
            collect($responses)->ensure(Response::class);
        }

        return new static($responses);
    }

    /**
     * @param  string|array<string, mixed>  $meta
     */
    public function withMeta(string|array $meta, mixed $value = null): static
    {
        $this->setMeta($meta, $value);

        return $this;
    }

    /**
     * @return Collection<int, Response>
     */
    public function responses(): Collection
    {
        return $this->responses;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }
}
