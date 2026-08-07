<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use InvalidArgumentException;
use Laravel\Mcp\Enums\ResultType;
use Laravel\Mcp\Support\InputRequests;

class InputRequired
{
    /**
     * @param  array<string, array<string, mixed>>  $inputRequests
     */
    public function __construct(
        protected array $inputRequests = [],
        protected ?string $requestState = null,
    ) {
        if ($this->inputRequests === [] && $this->requestState === null) {
            throw new InvalidArgumentException('An input required result must carry input requests, request state, or both.');
        }

        foreach ($this->inputRequests as $key => $inputRequest) {
            $method = $inputRequest['method'] ?? null;

            if (! is_string($method) || ! array_key_exists($method, InputRequests::CAPABILITIES)) {
                throw new InvalidArgumentException("Input request [{$key}] must use one of [".implode(', ', array_keys(InputRequests::CAPABILITIES)).'].');
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $inputRequests
     */
    public static function make(array $inputRequests = [], ?string $requestState = null): static
    {
        return new static($inputRequests, $requestState);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function inputRequests(): array
    {
        return $this->inputRequests;
    }

    /**
     * @return array<int, string>
     */
    public function requiredCapabilities(): array
    {
        return array_values(array_unique(array_map(
            fn (array $inputRequest): string => InputRequests::CAPABILITIES[$inputRequest['method']],
            $this->inputRequests,
        )));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resultType' => ResultType::INPUT_REQUIRED->value,
            ...$this->inputRequests === [] ? [] : ['inputRequests' => $this->inputRequests],
            ...$this->requestState === null ? [] : ['requestState' => $this->requestState],
        ];
    }
}
