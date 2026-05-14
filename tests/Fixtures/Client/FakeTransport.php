<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client;

use Laravel\Mcp\Client\Contracts\Transport;
use RuntimeException;

class FakeTransport implements Transport
{
    public bool $connected = false;

    /** @var array<int, string> */
    public array $sent = [];

    /** @var array<int, string> */
    public array $responses = [];

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function send(string $message): void
    {
        $this->sent[] = $message;
    }

    public function receive(): string
    {
        if ($this->responses === []) {
            throw new RuntimeException('No queued responses in FakeTransport.');
        }

        return array_shift($this->responses);
    }
}
