<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class StdioTransport implements Transport
{
    protected ?Process $process = null;

    protected ?InputStream $input = null;

    protected string $buffer = '';

    protected float $timeoutSeconds = 30.0;

    /**
     * @param  array<int, string>  $args
     */
    public function __construct(protected string $command, protected array $args = [])
    {
        //
    }

    public function connect(): void
    {
        if ($this->process?->isRunning()) {
            return;
        }

        $this->input = new InputStream;
        $this->process = new Process([$this->command, ...$this->args]);
        $this->process->setInput($this->input);
        $this->process->setTimeout(null);

        try {
            $this->process->start();
        } catch (ExceptionInterface) {
            throw new ClientException("Failed to start process [{$this->command}]. Make sure the command exists.");
        }
    }

    public function disconnect(): void
    {
        $this->input?->close();
        $this->input = null;

        if ($this->process?->isRunning()) {
            $this->process->stop(0.1);
        }

        $this->process = null;
        $this->buffer = '';
    }

    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    public function send(string $message): void
    {
        if (! $this->input instanceof InputStream || ! $this->process?->isRunning()) {
            throw new ClientException('Transport is not connected.');
        }

        $this->input->write($message."\n");
    }

    public function receive(): string
    {
        if (! $this->process instanceof Process) {
            throw new ClientException('Transport is not connected.');
        }

        return $this->popLine() ?? $this->readNextLine($this->process);
    }

    protected function readNextLine(Process $process): string
    {
        $process->setIdleTimeout($this->timeoutSeconds);

        try {
            $found = $process->waitUntil($this->bufferUntilNewline(...));
        } catch (ProcessTimedOutException) {
            $this->failWith('Timed out while waiting for server response.');
        }

        if (! $found) {
            $stderr = trim($process->getErrorOutput());
            $suffix = $stderr === '' ? '' : " stderr: {$stderr}";

            $this->failWith("Subprocess [{$this->command}] closed its output before sending a complete response.{$suffix}");
        }

        return $this->popLine() ?? $this->failWith('Subprocess output stream did not yield a complete line.');
    }

    protected function bufferUntilNewline(string $type, string $chunk): bool
    {
        if ($type !== Process::OUT) {
            return false;
        }

        $this->buffer .= $chunk;

        return str_contains($this->buffer, "\n");
    }

    protected function popLine(): ?string
    {
        $newlinePos = strpos($this->buffer, "\n");

        if ($newlinePos === false) {
            return null;
        }

        $line = substr($this->buffer, 0, $newlinePos + 1);
        $this->buffer = substr($this->buffer, $newlinePos + 1);

        return $line;
    }

    protected function failWith(string $message): never
    {
        $this->disconnect();

        throw new ClientException($message);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
