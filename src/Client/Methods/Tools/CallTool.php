<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\MirrorsParameters;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Support\MirroredParameters;

/**
 * @implements Method<ToolResult>
 */
class CallTool implements Method, MirrorsParameters
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        protected string $name,
        protected array $arguments = [],
        protected ?MirroredParameters $mirroredParameters = null,
    ) {
        //
    }

    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array
    {
        return $this->mirroredParameters?->headers($this->arguments) ?? [];
    }

    public function method(): string
    {
        return 'tools/call';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'name' => $this->name,
            'arguments' => (object) $this->arguments,
        ];
    }

    public function handle(Protocol $protocol): ToolResult
    {
        return ToolResult::from($protocol->dispatch($this));
    }
}
