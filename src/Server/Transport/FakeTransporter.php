<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FakeTransporter implements Transport
{
    public function onReceive(Closure $handler): void
    {
        //
    }

    public function send(string $message): void
    {
        //
    }

    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array
    {
        return [];
    }

    public function run(): Response|StreamedResponse
    {
        throw new LogicException('Not implemented.');
    }

    public function stream(Closure $stream): void
    {
        //
    }
}
