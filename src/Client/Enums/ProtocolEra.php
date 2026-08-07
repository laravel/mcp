<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Enums;

enum ProtocolEra: string
{
    case AUTO = 'auto';
    case MODERN = 'modern';
    case LEGACY = 'legacy';
}
