<?php

namespace Laravel\Mcp\Transport;

class StdioTransport implements Transport
{
    private $handler;
    private $stdin;
    private $stdout;

    public function __construct($stdin, $stdout)
    {
        $this->stdin = $stdin;
        $this->stdout = $stdout;
    }

    public function onReceive(callable $handler)
    {
        $this->handler = $handler;
    }

    public function send(string $message)
    {
        fwrite($this->stdout, $message . PHP_EOL);
    }

    public function run()
    {
        while ($line = fgets($this->stdin)) {
            ($this->handler)($line);
        }
    }
}
