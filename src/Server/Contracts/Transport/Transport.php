<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts\Transport;

use Closure;

interface Transport
{
    /**
     * Register the callback to handle incoming messages.
     */
    public function onReceive(callable $handler): void;

    /**
     * Run the transport and process the request.
     */
    public function run();

    /**
     * Send a message to the transport.
     */
    public function send(string $message): void;

    /**
     * Get the session ID.
     */
    public function sessionId(): ?string;

    /**
     * Stream the yielded values from the callback.
     */
    public function stream(Closure $stream): void;
}
