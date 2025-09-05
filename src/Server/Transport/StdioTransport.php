<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Laravel\Mcp\Server\Contracts\Transport;

class StdioTransport implements Transport
{
    /**
     * @param  (Closure(string): void)|null  $handler
     */
    public function __construct(
        protected string $sessionId,
        protected ?Closure $handler = null,
    ) {
        //
    }

    /**
     * Register the server handler to handle incoming messages.
     */
    public function onReceive(Closure $handler): void
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
