<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process as SymfonyProcess;

class StdioTransport implements Transport
{
    protected ?InvokedProcess $process = null;

    protected ?InputStream $input = null;

    protected string $buffer = '';

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
        if ($this->process?->running()) {
            return;
        }

        $this->input = new InputStream;

        try {
            $this->process = Process::input($this->input)
                ->timeout(0)
                ->idleTimeout(0)
                ->start(
                    array_merge([$this->command], $this->args),
                    function (string $type, string $chunk): void {
                        if ($type === SymfonyProcess::OUT) {
                            $this->buffer .= $chunk;
                        }
                    },
                );
        } catch (ExceptionInterface $exceptionInterface) {
            throw new ClientException(
                "Failed to start process [{$this->command}]. Make sure the command exists ".
                'and is reachable via an absolute path or the PATH of the running PHP process.'
            );
        }
    }

    public function disconnect(): void
    {
        $this->input?->close();
        $this->input = null;

        if ($this->process?->running()) {
            $this->process->stop(0.1);
        }

        $this->process = null;
        $this->buffer = '';
    }

    public function send(string $message): void
    {
        if ($this->input === null || ! $this->process?->running()) {
            throw new ClientException('Transport is not connected.');
        }

        $this->input->write($message.PHP_EOL);
    }

    public function receive(?float $timeoutSeconds = null): string
    {
        if ($this->process === null) {
            throw new ClientException('Transport is not connected.');
        }

        $deadline = $timeoutSeconds === null ? null : microtime(true) + $timeoutSeconds;

        while (true) {
            $newlinePos = strpos($this->buffer, "\n");

            if ($newlinePos !== false) {
                $line = substr($this->buffer, 0, $newlinePos + 1);
                $this->buffer = substr($this->buffer, $newlinePos + 1);

                return $line;
            }

            if (! $this->process->running()) {
                $stderr = trim($this->process->errorOutput());
                $stderrPart = $stderr === '' ? '' : " stderr: {$stderr}";

                throw new ClientException("Subprocess [{$this->command}] closed its output before sending a complete response.{$stderrPart}");
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                throw new ClientException('Timed out while waiting for server response.');
            }

            usleep(20_000);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
