<?php

namespace Laravel\Mcp\Transport;

use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;

class HttpSseTransport implements Transport
{
    private $handler;
    private Request $request;
    private string $channel;
    private bool $publishing;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->publishing = $request->isMethod('post');
        $this->channel = $request->query('session') ?: Uuid::uuid4()->toString();
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $message)
    {
        Redis::rpush("mcp:sessions:{$this->channel}", $message);
    }

    public function run()
    {
        if ($this->publishing) {
            ($this->handler)($this->request->getContent());

            return response('', 204);
        }

        $endpoint = $this->request->path().'/messages?session='.$this->channel;

        $callback = function () use ($endpoint) {
            yield new StreamedEvent(
                event: 'endpoint',
                data: $endpoint,
            );

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $hit = Redis::blpop(["mcp:sessions:{$this->channel}"], 10);

                if ($message = $hit[1] ?? null) {
                    yield new StreamedEvent(
                        event: 'message',
                        data: $message,
                    );
                }
            }
        };

        return response()->eventStream($callback);
    }
}
