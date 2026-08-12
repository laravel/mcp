<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Closure;

interface Transport
{
    public function onReceive(Closure $handler): void;

    public function run(); // @phpstan-ignore-line

    public function send(string $message): void;

    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array;

    public function stream(Closure $stream): void;
}
