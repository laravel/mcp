<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\ClientException;

class StdioTransport implements Transport
{
    /** @var resource|null */
    protected $process;

    /** @var array<int, resource|null> */
    protected array $pipes = [];

    /**
     * @param  array<int, string>  $args
     */
    public function __construct(
        protected string $command,
        protected array $args = [],
    ) {
        //
    }

    public function connect(): void
    {
        if (is_resource($this->process)) {
            return;
        }

        $process = proc_open(
            array_merge([$this->command], $this->args),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new ClientException("Failed to start process [{$this->command}].");
        }

        $this->process = $process;
        $this->pipes = $pipes;
    }

    public function disconnect(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;
    }

    public function send(string $message): void
    {
        $stdin = $this->pipes[0] ?? null;

        if (! is_resource($stdin)) {
            throw new ClientException('Transport is not connected.');
        }

        fwrite($stdin, $message.PHP_EOL);
        fflush($stdin);
    }

    public function receive(): string
    {
        $stdout = $this->pipes[1] ?? null;

        if (! is_resource($stdout)) {
            throw new ClientException('Transport is not connected.');
        }

        $line = fgets($stdout);

        if ($line === false) {
            throw new ClientException('Transport closed while reading.');
        }

        return $line;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
