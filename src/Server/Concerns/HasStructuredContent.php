<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

trait HasStructuredContent
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $structuredContent = null;

    /**
     * @param  array<string, mixed>  $structuredContent
     */
    public function setStructuredContent(array $structuredContent): void
    {
        $this->structuredContent ??= [];

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
