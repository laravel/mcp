<?php

namespace Laravel\Mcp\Transport;

use Closure;
use Illuminate\Support\Str;
use Laravel\Mcp\Contracts\Transport\Transport;

class StdioTransport implements Transport
{
    /**
     * The server handler responsible for handling the request.
     */
    private $handler;

    /**
     * The standard input/output instance.
     */
    private Stdio $stdio;

    /**
     * The session ID of the request.
     */
    private string $sessionId;

    /**
     * Create a new STDIO transport.
     */
    public function __construct(Stdio $stdio)
    {
        $this->stdio = $stdio;
        $this->sessionId = Str::uuid()->toString();
    }

    /**
     * Register the server handler to handle incoming messages.
     */
    public function onReceive(callable $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Send a message to the client.
     */
    public function send(string $message): void
    {
        $this->stdio->write($message);
    }

    /**
     * Run the transport and process the request.
     */
    public function run(): void
    {
        while ($line = $this->stdio->read()) {
            ($this->handler)($line);
        }
    }

    /**
     * Get the session ID of the request.
     */
    public function sessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Stream the yielded values from the callback.
     */
    public function stream(Closure $stream): void
    {
        $stream();
    }
}
