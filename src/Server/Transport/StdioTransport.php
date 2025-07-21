<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Contracts\Transport\Transport;

class StdioTransport implements Transport
{
    /**
     * The server handler responsible for handling the request.
     *
     * @var callable
     */
    private $handler;

    /**
     * The session ID of the request.
     */
    private string $sessionId;

    /**
     * Create a new STDIO transport.
     */
    public function __construct()
    {
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
    public function send(string $message, ?string $sessionId = null): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }

    /**
     * Run the transport and process the request.
     */
    public function run(): void
    {
        stream_set_blocking(STDIN, false);

        while (! feof(STDIN)) {
            if (($line = fgets(STDIN)) === false) {
                usleep(10000);

                continue;
            }

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
