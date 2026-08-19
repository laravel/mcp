<?php

declare(strict_types=1);

namespace Laravel\Mcp\Enums;

enum ElicitationAction: string
{
    case Accept = 'accept';
    case Decline = 'decline';
    case Cancel = 'cancel';
}
