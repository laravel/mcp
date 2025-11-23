<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use InvalidArgumentException;

trait HasStructuredContent
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $structuredContent = null;

    /**
     * @param  array<string, mixed>|string  $structuredContent
     */
    public function setStructuredContent(array|string $structuredContent, mixed $value = null): void
    {
        $this->structuredContent ??= [];

        if (! is_array($structuredContent)) {
            if (is_null($value)) {
                throw new InvalidArgumentException('Value is required when using key-value signature.');
            }

            $this->structuredContent[$structuredContent] = $value;

            return;
        }

        $this->structuredContent = array_merge($this->structuredContent, $structuredContent);
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  T  $baseArray
     * @return T&array{structuredContent?: array<string, mixed>}
     */
    public function mergeStructuredContent(array $baseArray): array
    {
        return ($structuredContent = $this->structuredContent)
            ? [...$baseArray, 'structuredContent' => $structuredContent]
            : $baseArray;
    }
}
