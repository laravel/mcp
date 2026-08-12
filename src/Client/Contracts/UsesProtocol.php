<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

use Laravel\Mcp\Enums\ProtocolVersion;

interface UsesProtocol
{
    public function useProtocol(ProtocolVersion $protocolVersion): void;
}
