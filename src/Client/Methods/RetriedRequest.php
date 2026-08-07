<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Protocol;

/**
 * @implements Method<array<string, mixed>>
 */
class RetriedRequest implements Method
{
    /**
     * @param  Method<mixed>  $method
     * @param  array<string, mixed>  $inputResponses
     */
    public function __construct(
        protected Method $method,
        protected array $inputResponses = [],
        protected ?string $requestState = null,
    ) {
        //
    }

    public function method(): string
    {
        return $this->method->method();
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            ...$this->method->params(),
            ...$this->inputResponses === [] ? [] : ['inputResponses' => $this->inputResponses],
            ...$this->requestState === null ? [] : ['requestState' => $this->requestState],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Protocol $protocol): array
    {
        return $protocol->dispatch($this);
    }
}
