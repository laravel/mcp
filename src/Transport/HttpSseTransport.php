<?php

namespace Laravel\Mcp\Transport;

use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

class HttpSseTransport implements Transport
{
    private $handler;
    private Request $request;
    private string $sessionId;
    private bool $publishing;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->publishing = $request->isMethod('post');
        $this->sessionId = $request->query('session') ?: Uuid::uuid4()->toString();
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function messageKey(int $id): string
    {
        return "mcp:sessions:{$this->sessionId}:msg:{$id}";
    }

    public function nextIdKey(): string
    {
        return "mcp:sessions:{$this->sessionId}:next_id";
    }

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $message)
    {
        $counter = $this->nextIdKey();

        $id = Cache::increment($counter);

        if (! is_int($id) || $id === 0) {
            Cache::forever($counter, 1);
            $id = 1;
        }

        Cache::put($this->messageKey($id), $message, 3600);
    }

    public function run()
    {
        if ($this->publishing) {
            ($this->handler)($this->request->getContent());

            return response('', 204);
        }

        $endpoint = $this->request->path().'/messages?session='.$this->sessionId;

        $callback = function () use ($endpoint) {
            yield new StreamedEvent(
                event: 'endpoint',
                data: $endpoint,
            );

            $cursor = 0;

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $next = $cursor + 1;
                $message = Cache::pull($this->messageKey($next));

                if ($message !== null) {
                    yield new StreamedEvent(
                        event: 'message',
                        data: $message,
                    );

                    $cursor = $next;
                }

                usleep(100_000);
            }
        };

        return response()->eventStream($callback);
    }
}
