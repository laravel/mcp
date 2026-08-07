<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum ResultType: string
{
    case COMPLETE = 'complete';
    case INPUT_REQUIRED = 'input_required';
}
