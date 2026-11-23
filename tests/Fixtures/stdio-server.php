<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Transport\StdioTransport;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$received = $argv[1];

$transport = new StdioTransport(function (string $message) use ($received): void {
    file_put_contents($received, $message, FILE_APPEND);
});

$transport->run();

file_put_contents($received, "stdin closed\n", FILE_APPEND);
