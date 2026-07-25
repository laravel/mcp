<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum ErrorCode: int
{
    case METHOD_NOT_FOUND = -32601;
    case INVALID_PARAMS = -32602;
    case RESOURCE_NOT_FOUND_LEGACY = -32002;
    case HEADER_MISMATCH = -32020;
    case MISSING_REQUIRED_CLIENT_CAPABILITY = -32021;
    case UNSUPPORTED_PROTOCOL_VERSION = -32022;
}
