<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

use Laravel\Mcp\Enums\ProtocolVersion;

interface Transport
{
    public function connect(): void;

    public function disconnect(): void;

    public function send(string $message): void;

    public function receive(): string;

    public function setTimeoutSeconds(float $seconds): void;

    public function setProtocolVersion(ProtocolVersion $version): void;

    /**
     * @return array<string, mixed>
     */
    public function recipe(): array;
}
