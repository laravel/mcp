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

    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message, ?string $sessionId = null): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }

    public function run(): void
    {
        stream_set_blocking(STDIN, false);

        while (! feof(STDIN)) {
            if (($line = fgets(STDIN)) === false) {
                usleep(10000);

                continue;
            }

            if (is_callable($this->handler)) {
                ($this->handler)($line);
            }
        }
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function stream(Closure $stream): void
    {
        $stream();
    }
}
