<?php

namespace Laravel\Mcp;

class SessionContext
{
    public function __construct(
        public array $clientCapabilities = [],
        public bool $initialized = false
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
