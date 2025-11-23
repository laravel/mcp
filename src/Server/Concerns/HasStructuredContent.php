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
     * @param  array<string, mixed>  $baseArray
     * @return array<string, mixed>
     */
    public function mergeStructuredContent(array $baseArray): array
    {
        if ($this->structuredContent === null) {
            return $baseArray;
        }

        return array_merge($baseArray, ['structuredContent' => $this->structuredContent]);
    }
}
