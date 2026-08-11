<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client;

use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Enums\ProtocolVersion;
use RuntimeException;

class FakeTransport implements Transport
{
    public bool $connected = false;

    /** @var array<int, string> */
    public array $sent = [];

    /** @var array<int, string> */
    public array $responses = [];

    public ?string $repeatResponse = null;

    public float $timeoutSeconds = 30.0;

    public ?ProtocolVersion $protocolVersion = null;

    public bool $negotiates = false;

    protected string|int|null $pendingId = null;

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
        $frame = json_decode($message, true);
        $frame = is_array($frame) ? $frame : [];

        $id = $frame['id'] ?? null;

        if (is_string($frame['method'] ?? null) && (is_string($id) || is_int($id))) {
            $this->pendingId = $id;
        }

        if (! $this->negotiates && ($frame['method'] ?? null) === 'server/discover') {
            array_unshift($this->responses, (string) json_encode([
                'jsonrpc' => '2.0',
                'id' => $this->pendingId,
                'error' => ['code' => -32601, 'message' => 'Method not found.'],
            ]));

            return;
        }

        $this->sent[] = $message;
    }

    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    public function setProtocolVersion(ProtocolVersion $version): void
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
                return $this->answering($this->repeatResponse);
            }

            throw new RuntimeException('No queued responses in FakeTransport.');
        }

        return $this->answering(array_shift($this->responses));
    }

    protected function answering(string $raw): string
    {
        $frame = json_decode($raw, true);

        if (! is_array($frame) || ! array_key_exists('id', $frame)) {
            return $raw;
        }

        if (! array_key_exists('result', $frame) && ! array_key_exists('error', $frame)) {
            return $raw;
        }

        return (string) json_encode([...$frame, 'id' => $this->pendingId]);
    }
}
