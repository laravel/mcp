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

    protected ?string $stderrPath = null;

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

    public function receive(?float $timeoutSeconds = null): string
    {
        $stdout = $this->pipes[1] ?? null;

        if (! is_resource($stdout)) {
            throw new ClientException('Transport is not connected.');
        }

        if ($timeoutSeconds !== null) {
            $this->waitForReadable($stdout, $timeoutSeconds);
        }

        $line = fgets($stdout);

        if ($line === false) {
            throw new ClientException($this->subprocessFailureMessage());
        }

        return $line;
    }

    protected function subprocessFailureMessage(): string
    {
        $message = "Subprocess [{$this->command}] closed its output before sending a complete response.";

        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);

            if (! $status['running']) {
                if ($status['signaled']) {
                    $message .= " It was terminated by signal {$status['termsig']}.";
                } elseif ($status['exitcode'] !== -1) {
                    $message .= " It exited with code {$status['exitcode']}.";
                }
            }
        }

        if ($this->stderrPath !== null && is_file($this->stderrPath)) {
            $stderr = trim((string) @file_get_contents($this->stderrPath));

            if ($stderr !== '') {
                $message .= ' stderr: '.$stderr;
            }
        }

        return $message;
    }

    protected function waitForReadable(mixed $stream, float $timeoutSeconds): void
    {
        $timeoutSeconds = max(0.0, $timeoutSeconds);
        $seconds = (int) $timeoutSeconds;
        $microseconds = (int) (($timeoutSeconds - $seconds) * 1000000);
        $read = [$stream];
        $write = null;
        $except = null;

        $ready = stream_select($read, $write, $except, $seconds, $microseconds);

        if ($ready === false) {
            throw new ClientException('Failed while waiting for server response.');
        }

        if ($ready === 0) {
            throw new ClientException('Timed out while waiting for server response.');
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
