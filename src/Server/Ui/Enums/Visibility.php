<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui\Enums;

enum Visibility: string
{
    case Model = 'model';
    case App = 'app';
}
