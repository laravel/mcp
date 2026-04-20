<?php

namespace Tests\Fixtures;

use Laravel\Mcp\Client\Contracts\ClientTransport;
use Laravel\Mcp\Client\Exceptions\ConnectionException;

class FakeClientTransport implements ClientTransport
{
    /** @var array<int, string> */
    protected array $sentMessages = [];

    /** @var array<int, string> */
    protected array $notifications = [];

    protected bool $connected = false;

    /**
     * @param  array<int, string>  $responses
     */
    public function __construct(protected array $responses = []) {}

    public function queueResponse(string $response): static
    {
        $this->responses[] = $response;

        return $this;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function send(string $message): string
    {
        if (! $this->connected) {
            throw new ConnectionException('Not connected.');
        }

        $this->sentMessages[] = $message;

        return array_shift($this->responses) ?? '{}';
    }

    public function notify(string $message): void
    {
        if (! $this->connected) {
            throw new ConnectionException('Not connected.');
        }

        $this->notifications[] = $message;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * @return array<int, string>
     */
    public function sentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * @return array<int, string>
     */
    public function notifications(): array
    {
        return $this->notifications;
    }
}
