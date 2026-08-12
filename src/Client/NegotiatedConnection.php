<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Laravel\Mcp\Client\Schema\DiscoverResult;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Enums\ProtocolVersion;

class NegotiatedConnection
{
    public function __construct(
        public ProtocolVersion $protocolVersion,
        public DiscoverResult|InitializeResult $result,
    ) {}

    public function discoverResult(): ?DiscoverResult
    {
        return $this->result instanceof DiscoverResult ? $this->result : null;
    }

    public function initializeResult(): ?InitializeResult
    {
        return $this->result instanceof InitializeResult ? $this->result : null;
    }
}
