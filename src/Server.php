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
use Generator;

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

    public string $serverName = 'Laravel MCP Server';

    public string $serverVersion = '0.0.1';

    public string $instructions = 'This MCP server lets AI agents interact with my Laravel application.';

    public array $tools = [];

    public int $maxPaginationLength = 50;

    public int $defaultPaginationLength = 15;

    protected Transport $transport;

    protected array $registeredTools = [];

    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'ping' => Ping::class,
    ];

    public function __construct(protected SessionStore $sessionStore)
    {
        $this->registeredTools = $this->tools;
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
        $session = $this->sessionStore->get($sessionId);

        $context = new ServerContext(
            supportedProtocolVersions: $this->supportedProtocolVersion,
            serverCapabilities: $this->capabilities,
            serverName: $this->serverName,
            serverVersion: $this->serverVersion,
            instructions: $this->instructions,
            tools: $this->registeredTools,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
        );

        try {
            $message = JsonRpcMessage::fromJson($rawMessage);

            if ($message->method === 'initialize') {
                return $this->handleInitializeMessage($sessionId, $message, $context);
            }

            if ($message->method === 'notifications/initialized') {
                return $this->handleInitializedNotificationMessage($sessionId, $session);
            }

            if (! $session) {
                throw new JsonRpcException(
                    'Session not found or not initialized.',
                    -32601,
                    isset($message->id) ? $message->id : null
                );
            }

            if (! isset($message->id) || $message->id === null) {
                return; // JSON-RPC notification, no response needed
            }

            if (! $session->initialized && $message->method !== 'ping') {
                throw new JsonRpcException("Session not initialized.", -32601, $message->id);
            }

            if (! isset($this->methods[$message->method])) {
                throw new JsonRpcException("Method not found: {$message->method}", -32601, $message->id);
            }

            $this->handleMessage($sessionId, $message, $session, $context);
        } catch (JsonRpcException $e) {
            $this->transport->send(json_encode($e->toJsonRpcError()), $sessionId);
        }
    }

    public function boot()
    {
        // Override this method to dynamically add tools, custom methods, etc., when the server boots.
    }

    public function addTool($tool)
    {
        if (! in_array($tool, $this->registeredTools)) {
            $this->registeredTools[] = $tool;
        }
    }

    public function addMethod(string $name, string $handlerClass)
    {
        $this->methods[$name] = $handlerClass;
    }

    private function handleMessage(string $sessionId, JsonRpcMessage $message, SessionContext $session, ServerContext $context)
    {
        $methodClass = $this->methods[$message->method];

        $response = (new $methodClass())->handle($message, $session, $context);

        if ($response instanceof Generator) {
            $this->transport->stream(function() use ($response, $sessionId) {
                foreach ($response as $message) {
                    $this->transport->send($message->toJson(), $sessionId);
                }
            });

            return;
        }

        return $this->transport->send($response->toJson(), $sessionId);
    }

    private function handleInitializeMessage(string $sessionId, JsonRpcMessage $message, ServerContext $context)
    {
        $session = new SessionContext(
            clientCapabilities: $message->params['capabilities'] ?? [],
        );

        $response = (new Initialize())->handle($message, $session, $context);

        $this->sessionStore->put($sessionId, $session);

        $this->transport->send($response->toJson(), $sessionId);
    }

    private function handleInitializedNotificationMessage(string $sessionId, SessionContext $session)
    {
        $session->initialized = true;
        $this->sessionStore->put($sessionId, $session);

        return;
    }
}
