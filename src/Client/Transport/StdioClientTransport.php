<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Laravel\Mcp\Client\Contracts\ClientTransport;
use Laravel\Mcp\Client\Exceptions\ConnectionException;

class StdioClientTransport implements ClientTransport
{
    /** @var resource|null */
    protected $process;

    /** @var array<int, resource> */
    protected array $pipes = [];

    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    public function __construct(
        protected string $command,
        protected array $args = [],
        protected ?string $workingDirectory = null,
        protected array $env = [],
        protected float $timeout = 30,
    ) {
        //
    }

    public function connect(): void
    {
        $command = implode(' ', array_map(escapeshellarg(...), [$this->command, ...$this->args]));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $this->env !== [] ? $this->env : null;

        $process = proc_open($command, $descriptors, $this->pipes, $this->workingDirectory, $env);

        if (! is_resource($process)) {
            throw new ConnectionException("Failed to start process: {$command}");
        }

        $this->process = $process;

        stream_set_blocking($this->pipes[1], false);
    }

    public function send(string $message): string
    {
        $this->ensureConnected();

        fwrite($this->pipes[0], $message."\n");
        fflush($this->pipes[0]);

        $startTime = microtime(true);

        while (true) {
            $response = fgets($this->pipes[1]);

            if ($response !== false) {
                return trim($response);
            }

            if ((microtime(true) - $startTime) >= $this->timeout) {
                throw new ConnectionException("Read timeout after {$this->timeout} seconds.");
            }

            usleep(10000);
        }
    }

    public function notify(string $message): void
    {
        $this->ensureConnected();

        fwrite($this->pipes[0], $message."\n");
        fflush($this->pipes[0]);
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
            proc_close($this->process);
        }

        $this->process = null;
    }

    public function isConnected(): bool
    {
        if (! is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);

        return $status['running'];
    }

    protected function ensureConnected(): void
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to process.');
        }
    }
}
