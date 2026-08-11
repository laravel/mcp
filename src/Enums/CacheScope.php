<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum CacheScope: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}
