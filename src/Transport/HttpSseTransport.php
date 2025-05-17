<?php

namespace Laravel\Mcp\Transport;

use Illuminate\Http\Request;
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

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        $endpoint = $this->request->path().'/messages?session='.$this->channel;

        $callback = function () use ($endpoint) {
            echo "event: endpoint\n";
            echo 'data: '.$endpoint."\n\n";
            flush();

            while (true) {
                $hit = Redis::blpop(["mcp:sessions:{$this->channel}"], 10);
                if ($hit) {
                    echo "data: {$hit[1]}\n\n";
                    flush();
                }

                if (connection_aborted()) {
                    break;
                }
            }
        };

        return response()->stream($callback, 200, $headers);
    }
}
