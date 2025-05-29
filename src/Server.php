<?php

namespace Laravel\Mcp;

use Laravel\Mcp\Methods\CallTool;
use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\Methods\Ping;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Contracts\Transport\Transport;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Session\SessionStore;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Transport\JsonRpcResponse;

abstract class Server
{
    public array $supportedProtocolVersion = [
        '2025-03-26',
    ];

    public array $capabilities = [
        'tools' => [
            'listChanged' => false,
        ],
    ];

    public string $serverName = 'My Laravel MCP Server';

    public string $serverVersion = '0.1.0';

    public string $instructions = 'Welcome to my Laravel MCP Server!';

    public array $tools = [];

    protected Transport $transport;

    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'ping' => Ping::class,
    ];

    public function __construct(protected SessionStore $sessionStore)
    {
    }

    public function connect(Transport $transport)
    {
        $this->transport = $transport;

        $this->boot();

        $this->transport->onReceive(fn ($message) => $this->handle($message));
    }

    public function handle(string $rawMessage)
    {
        $sessionId = $this->transport->sessionId() ?? Str::uuid()->toString();
        $context = $sessionId ? $this->sessionStore->get($sessionId) : null;

        try {
            $message = JsonRpcMessage::fromJson($rawMessage);

            if (! $context && $message->method === 'initialize') {
                return $this->handleInitializeMessage($sessionId, $message);
            }

            if ($message->method === 'notifications/initialized') {
                return $this->handleInitializedNotificationMessage($sessionId, $context);
            }

            if (! isset($message->id) || $message->id === null) {
                return; // This is a generic notification, we'll ignore for now
            }

            if (! $context->initialized && $message->method !== 'ping') {
                throw new JsonRpcException("Not initialized.", -32002, $message->id);
            }

            if (! isset($this->methods[$message->method])) {
                throw new JsonRpcException("Method not found: {$message->method}", -32601, $message->id);
            }

            $this->handleMessage($sessionId, $message, $context);
        } catch (JsonRpcException $e) {
            $this->transport->send(json_encode($e->toJsonRpcError()), $sessionId);
        } catch (ValidationException $e) {
            $response = JsonRpcResponse::create(
                $message->id,
                (new ToolResponse($e->getMessage(), true))->toArray()
            );

            $this->transport->send($response->toJson(), $sessionId);
        }
    }

    public function boot()
    {
        // Override this method to add custom methods, etc., when the server boots.
    }

    public function addMethod(string $name, string $handlerClass)
    {
        $this->methods[$name] = $handlerClass;
    }

    private function handleMessage(string $sessionId, JsonRpcMessage $message, SessionContext $context)
    {
        $methodClass = $this->methods[$message->method];

        $methodHandler = new $methodClass();

        $response = $methodHandler->handle($message, $context);

        $this->transport->send($response->toJson(), $sessionId);
    }

    private function handleInitializeMessage(string $sessionId, JsonRpcMessage $message)
    {
        $context = new SessionContext(
            supportedProtocolVersions: $this->supportedProtocolVersion,
            clientCapabilities: $message->params['capabilities'] ?? [],
            serverCapabilities: $this->capabilities,
            serverName: $this->serverName,
            serverVersion: $this->serverVersion,
            instructions: $this->instructions,
            tools: $this->tools
        );

        $response = (new Initialize())->handle($message, $context);

        $this->sessionStore->put($sessionId, $context);

        $this->transport->send($response->toJson(), $sessionId);
    }

    private function handleInitializedNotificationMessage(string $sessionId, SessionContext $context)
    {
        $context->initialized = true;
        $this->sessionStore->put($sessionId, $context);

        return;
    }
}
