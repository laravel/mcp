<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum Role: string
{
    case ASSISTANT = 'assistant';
    case USER = 'user';
}
