<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Closure;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;

interface Transport
{
    public function onReceive(Closure $handler): void;

    public function run(); // @phpstan-ignore-line

    public function send(string $message, ?string $sessionId = null): void;

    public function sessionId(): ?string;

    public function stream(Closure $stream): void;

    /**
     * Send a JSON-RPC request to the client and wait for the response.
     *
     * @throws JsonRpcException
     */
    public function sendRequest(string $message): string;
}
