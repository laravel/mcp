<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui\Enum;

enum Permission: string
{
    case Camera = 'camera';
    case Microphone = 'microphone';
    case Geolocation = 'geolocation';
    case ClipboardWrite = 'clipboardWrite';
}
