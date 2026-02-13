<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

interface ClientTransport
{
    public function send(string $message): string;

    public function notify(string $message): void;

    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;
}
