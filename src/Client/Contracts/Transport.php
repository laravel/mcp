<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

interface Transport
{
    public function connect(): void;

    public function disconnect(): void;

    /**
     * @param  array<string, string>  $headers
     */
    public function send(string $message, array $headers = []): void;

    public function receive(): string;

    public function setTimeoutSeconds(float $seconds): void;

    public function setProtocolVersion(string $version): void;

    /**
     * @return array<string, mixed>
     */
    public function recipe(): array;
}
