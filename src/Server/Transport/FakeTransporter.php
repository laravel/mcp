<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Closure;
use Illuminate\Http\Response;
use Laravel\Mcp\Server\Contracts\Transport;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FakeTransporter implements Transport
{
    /**
     * @var array<int, string>
     */
    protected array $elicitationResponses = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $sentElicitations = [];

    /**
     * @var array<int, string>
     */
    protected array $sentMessages = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $clientCapabilities = null;

    public function onReceive(Closure $handler): void
    {
        //
    }

    public function send(string $message, ?string $sessionId = null): void
    {
        $this->sentMessages[] = $message;
    }

    public function run(): Response|StreamedResponse
    {
        throw new LogicException('Not implemented.');
    }

    public function sessionId(): ?string
    {
        return uniqid();
    }

    public function stream(Closure $stream): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function expectElicitation(array $response): void
    {
        $this->elicitationResponses[] = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => '_placeholder_',
            'result' => $response,
        ]);
    }

    public function sendRequest(string $message): string
    {
        $request = json_decode($message, true);
        $this->sentElicitations[] = $request;

        if ($this->elicitationResponses === []) {
            throw new LogicException('No elicitation responses queued. Call expectElicitation() first.');
        }

        $response = json_decode(array_shift($this->elicitationResponses), true);
        $response['id'] = $request['id'];

        return (string) json_encode($response);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sentElicitations(): array
    {
        return $this->sentElicitations;
    }

    /**
     * @return array<int, string>
     */
    public function sentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * @param  array<string, mixed>|null  $capabilities
     */
    public function setClientCapabilities(?array $capabilities): void
    {
        $this->clientCapabilities = $capabilities;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientCapabilities(): ?array
    {
        return $this->clientCapabilities;
    }
}
