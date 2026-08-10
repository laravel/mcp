<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Contracts\MirrorsParameters;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Support\ToolHeaders;

/**
 * @implements Method<ToolResult>
 */
class CallTool implements Method, MirrorsParameters
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $inputSchema
     */
    public function __construct(
        protected string $name,
        protected array $arguments = [],
        protected array $inputSchema = [],
    ) {
        //
    }

    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array
    {
        if ($this->inputSchema === [] || ToolHeaders::invalid($this->inputSchema) !== null) {
            return [];
        }

        return ToolHeaders::extract($this->inputSchema, $this->arguments);
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
