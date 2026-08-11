<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum CacheScope: string
{
    case Public = 'public';
    case Private = 'private';
}
