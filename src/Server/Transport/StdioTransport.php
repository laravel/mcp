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
        protected ?Closure $handler = null,
    ) {
        //
    }

    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }

    public function run(): void
    {
        $input = $this->inputStream();

        while (! feof($input)) {
            if (($line = fgets($input)) === false) {
                usleep(10000);

                continue;
            }

            if (is_callable($this->handler)) {
                ($this->handler)($line);
            }
        }
    }

    public function stream(Closure $stream): void
    {
        $stream();
    }

    /**
     * Get the stream to read messages from, using a blocking pipe on Windows so idle reads never report a false end of input.
     *
     * @return resource
     */
    protected function inputStream()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $stream = fopen('php://stdin', 'rb', false, stream_context_create([
                'pipe' => ['blocking' => true],
            ]));

            if ($stream !== false) {
                return $stream;
            }
        }

        stream_set_blocking(STDIN, false);

        return STDIN;
    }
}
