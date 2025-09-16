<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FakeTransporter implements Transport
{
    /**
     * @var (Closure(string): void)|null
     */
    protected ?Closure $handler = null;

    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message, ?string $sessionId = null): void
    {
        // Fake implementation - do nothing
    }

    public function run(): Response|StreamedResponse
    {
        if (is_callable($this->handler)) {
            ($this->handler)('');
        }

        return response('', 200, ['Content-Type' => 'application/json']);
    }

    public function sessionId(): ?string
    {
        return uniqid();
    }

    public function stream(Closure $stream): void
    {
        // Fake implementation - do nothing
    }
}
