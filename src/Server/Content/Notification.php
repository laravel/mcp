<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content;

use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ResourceTemplate;
use Laravel\Mcp\Server\Tool;

class Notification implements Content
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(protected string $method, protected array $params)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toResource(Resource|ResourceTemplate $resource): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'params' => $this->params,
        ];
    }
}
