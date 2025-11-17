<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Stringable;

/**
 * @extends Arrayable<string, mixed>
 */
interface Content extends Arrayable, Stringable
{
    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array;

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array;

    /**
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array;

    /**
     * @param  array<string, mixed>|string  $meta
     */
    public function setMeta(array|string $meta, mixed $value = null): void;

    public function __toString(): string;
}
