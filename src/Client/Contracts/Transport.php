<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

interface Transport
{
    public function connect(): void;

    public function disconnect(): void;

    public function send(string $message): void;

    public function receive(): string;

    public function setTimeoutSeconds(float $seconds): void;

    /**
     * @return array<string, mixed>
     */
    public function recipe(): array;
}
