<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpTransport implements Transport
{
    /**
     * @param  (Closure(string): void)|null  $handler
     */
    public function __construct(
        protected Request $request,
        protected ?Closure $handler = null,
        protected ?string $reply = null,
        protected ?Closure $stream = null,
    ) {
        //
    }

    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    public function send(string $message): void
    {
        if ($this->stream instanceof Closure) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
    }

    public function run(): Response|StreamedResponse
    {
        if (is_callable($this->handler)) {
            ($this->handler)($this->request->getContent());
        }

        if ($this->stream instanceof Closure) {
            $stream = $this->stream;

            return response()->stream(function () use ($stream): void {
                $result = $stream();

                if (! is_iterable($result)) {
                    return;
                }

                foreach ($result as $message) {
                    if (connection_aborted() !== 0) {
                        return;
                    }

                    $this->sendStreamMessage((string) $message);
                }
            }, 200, $this->getHeaders());
        }

        // Must be 202 - https://modelcontextprotocol.io/specification/2025-06-18/basic/transports#sending-messages-to-the-server
        $statusCode = $this->reply === null ? 202 : 200;
        $response = response($this->reply, $statusCode, $this->getHeaders());

        assert($response instanceof Response);

        return $response;
    }

    /**
     * Register a streaming callback.
     *
     * The callback may echo SSE-formatted output directly or return an iterable of message payloads.
     *
     * @param  Closure(): (iterable<string>|void)  $stream
     */
    public function stream(Closure $stream): void
    {
        $this->stream = $stream;
    }

    protected function sendStreamMessage(string $message): void
    {
        echo 'data: '.$message."\n\n";

        if (ob_get_level() !== 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        $headers = [
            'Content-Type' => $this->stream instanceof Closure ? 'text/event-stream' : 'application/json',
        ];

        if ($this->stream instanceof Closure) {
            $headers['X-Accel-Buffering'] = 'no';
        }

        return $headers;
    }
}
