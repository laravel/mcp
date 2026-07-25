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

    public ?string $repeatResponse = null;

    /**
     * The canned reply for the modern [server/discover] probe. When null, the
     * fake behaves like a legacy server and rejects the probe with -32601.
     */
    public ?string $discoverResponse = null;

    public float $timeoutSeconds = 30.0;

    public ?string $protocolVersion = null;

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

        $decoded = json_decode($message, true);

        if (is_array($decoded) && ($decoded['method'] ?? null) === 'server/discover') {
            array_unshift($this->responses, $this->discoverReply($decoded['id'] ?? null));
        }
    }

    protected function discoverReply(mixed $id): string
    {
        if ($this->discoverResponse !== null) {
            $reply = json_decode($this->discoverResponse, true);
            $reply['id'] = $id;

            return json_encode($reply);
        }

        return json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32601,
                'message' => 'The method [server/discover] was not found.',
            ],
        ]);
    }

    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    public function setProtocolVersion(string $version): void
    {
        $this->protocolVersion = $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function recipe(): array
    {
        return ['driver' => 'fake'];
    }

    public function receive(): string
    {
        if ($this->responses === []) {
            if ($this->repeatResponse !== null) {
                return $this->repeatResponse;
            }

            throw new RuntimeException('No queued responses in FakeTransport.');
        }

        return array_shift($this->responses);
    }
}
