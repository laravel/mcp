<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum ProtocolHandshake
{
    case Initialize;
    case Discovery;
}
