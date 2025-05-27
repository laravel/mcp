<?php

namespace Laravel\Mcp\Tests\Unit;

use Laravel\Mcp\Tests\Fixtures\ExampleServer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laravel\Mcp\Tests\Fixtures\ArrayTransport;

class ServerTest extends TestCase
{
    #[Test]
    public function it_can_handle_an_initialize_message()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->connect($transport);

        $payload = json_encode($this->initializeMessage());

        ($transport->handler)($payload);

        $response = json_decode($transport->sent[0], true);

        $this->assertEquals($this->expectedInitializeResponse(), $response);
    }

    #[Test]
    public function it_can_handle_a_list_tools_message()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->connect($transport);

        $payload = json_encode($this->listToolsMessage());

        ($transport->handler)($payload);

        $response = json_decode($transport->sent[0], true);

        $this->assertEquals($this->expectedListToolsResponse(), $response);
    }

    #[Test]
    public function it_can_handle_a_call_tool_message()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->connect($transport);

        $payload = json_encode($this->callToolMessage());

        ($transport->handler)($payload);

        $response = json_decode($transport->sent[0], true);

        $this->assertEquals($this->expectedCallToolResponse(), $response);
    }

    #[Test]
    public function it_can_handle_a_notification_message()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();
        
        $server->connect($transport);

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        ($transport->handler)($payload);

        $this->assertCount(0, $transport->sent);
    }

    #[Test]
    public function it_can_handle_an_unknown_method()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->connect($transport);

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 789,
            'method' => 'unknown/method',
            'params' => [],
        ]);

        ($transport->handler)($payload);

        $response = json_decode($transport->sent[0], true);

        $this->assertEquals([
            'jsonrpc' => '2.0',
            'id' => 789,
            'error' => [
                'code' => -32601,
                'message' => 'Method not found: unknown/method',
            ],
        ], $response);
    }

    #[Test]
    public function it_handles_json_decode_errors()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->connect($transport);

        $invalidJsonPayload = '{"jsonrpc": "2.0", "id": 123, "method": "initialize", "params": {}'; // Malformed JSON

        ($transport->handler)($invalidJsonPayload);

        $this->assertCount(1, $transport->sent);
        $response = json_decode($transport->sent[0], true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertNull($response['id']);
        $this->assertEquals(-32700, $response['error']['code']);
        $this->assertEquals('Parse error', $response['error']['message']);
    }

    #[Test]
    public function it_can_handle_a_custom_method_message()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->addMethod('custom/method', \Laravel\Mcp\Tests\Fixtures\CustomMethodHandler::class);

        $server->connect($transport);

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 12345,
            'method' => 'custom/method',
            'params' => [],
        ]);

        ($transport->handler)($payload);

        $this->assertCount(1, $transport->sent);
        $response = json_decode($transport->sent[0], true);

        $this->assertEquals([
            'jsonrpc' => '2.0',
            'id' => 12345,
            'result' => [
                'message' => 'Custom method executed successfully!',
            ],
        ], $response);
    }

    #[Test]
    public function it_can_handle_a_ping_message()
    {
        $transport = new ArrayTransport();
        $server = new ExampleServer();

        $server->connect($transport);

        $payload = json_encode($this->pingMessage());

        ($transport->handler)($payload);

        $response = json_decode($transport->sent[0], true);

        $this->assertEquals($this->expectedPingResponse(), $response);
    }

    private function initializeMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 456,
            'method' => 'initialize',
            'params' => [],
        ];
    }

    private function expectedInitializeResponse(): array
    {
        $server = new ExampleServer();

        return [
            'jsonrpc' => '2.0',
            'id' => 456,
            'result' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => $server->capabilities,
                'serverInfo' => [
                    'name' => $server->serverName,
                    'version' => $server->serverVersion,
                ],
                'instructions' => $server->instructions,
            ],
        ];
    }

    private function listToolsMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ];
    }

    private function expectedListToolsResponse(): array
    {
        return [
            "jsonrpc" => "2.0",
            "id" => 1,
            "result" => [
                "tools" => [
                    [
                        "name" => "hello-tool",
                        "description" => "This tool says hello to a person",
                        "inputSchema" => [
                            "type" => "object",
                            "properties" => [
                                "name" => [
                                    "type" => "string",
                                    "description" => "The name of the person to greet"
                                ]
                            ],
                            "required" => ["name"]
                        ]
                    ],
                ],
            ]
        ];
    }

    private function callToolMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'hello-tool',
                'arguments' => [
                    'name' => 'John Doe',
                ],
            ],
        ];
    }

    private function expectedCallToolResponse(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'content' => [[
                    'type' => 'text',
                    'text' => 'Hello, John Doe!',
                ]],
                'isError' => false,
            ],
        ];
    }

    private function pingMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 789,
            'method' => 'ping',
        ];
    }

    private function expectedPingResponse(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 789,
            'result' => [],
        ];
    }
}