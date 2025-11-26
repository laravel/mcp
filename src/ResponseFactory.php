<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Concerns\HasStructuredContent;

class ResponseFactory
{
    use Conditionable;
    use HasMeta;
    use HasStructuredContent;
    use Macroable;

    /**
     * @var Collection<int, Response>
     */
    protected Collection $responses;

    /**
     * @param  Response|array<int, Response>  $responses
     */
    public function __construct(Response|array $responses)
    {
        $wrapped = Arr::wrap($responses);

        foreach ($wrapped as $index => $response) {
            if (! $response instanceof Response) {
                throw new InvalidArgumentException(
                    "Invalid response type at index {$index}: Expected ".Response::class.', but received '.get_debug_type($response).'.'
                );
            }
        }

        $this->responses = collect($wrapped);
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
     * @param  array<string, mixed>  $structuredContent
     */
    public function withStructuredContent(array $structuredContent): static
    {
        $this->setStructuredContent($structuredContent);

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

    /**
     * @return array<string, mixed>|null
     */
    public function getStructuredContent(): ?array
    {
        return $this->structuredContent;
    }
}
