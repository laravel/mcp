<?php

namespace Laravel\Mcp\Tests\Feature;

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\McpServiceProvider;
use Orchestra\Testbench\TestCase;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;
use PHPUnit\Framework\Attributes\Test;
use Laravel\Mcp\Support\FakeStdio;
use Laravel\Mcp\Support\Stdio;

class McpServerTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,
        ];
    }

    #[Test]
    public function it_can_initialize_a_connection_over_http()
    {
        Mcp::web('test-mcp-init', ExampleServer::class);

        $response = $this->postJson('test-mcp-init', $this->initializeMessage());

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals($this->expectedInitializeResponse(), $responseData);
    }

    #[Test]
    public function it_can_list_tools_over_http()
    {
        Mcp::web('test-mcp-list', ExampleServer::class);

        $response = $this->postJson('test-mcp-list', $this->listToolsMessage());

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals($this->expectedListToolsResponse(), $responseData);
    }

    #[Test]
    public function it_can_call_a_tool_over_http()
    {
        Mcp::web('test-mcp-call', ExampleServer::class);

        $response = $this->postJson('test-mcp-call', $this->callToolMessage());

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals($this->expectedCallToolResponse(), $responseData);
    }

    #[Test]
    public function it_can_initialize_a_connection_over_stdio()
    {
        $stdio = new FakeStdio();
        $stdio->withInput(json_encode($this->initializeMessage()));

        $this->app->instance(Stdio::class, $stdio);

        Mcp::cli('test-mcp-init', ExampleServer::class);

        $this->artisan('mcp:test-mcp-init');

        $this->assertEquals(
            $this->expectedInitializeResponse(),
            json_decode($stdio->getOutput(), true),
        );
    }

    #[Test]
    public function it_can_list_tools_over_stdio()
    {
        $stdio = new FakeStdio();
        $stdio->withInput(json_encode($this->listToolsMessage()));

        $this->app->instance(Stdio::class, $stdio);

        Mcp::cli('test-mcp-list', ExampleServer::class);

        $this->artisan('mcp:test-mcp-list');

        $this->assertEquals(
            $this->expectedListToolsResponse(),
            json_decode($stdio->getOutput(), true),
        );
    }

    #[Test]
    public function it_can_call_a_tool_over_stdio()
    {
        $stdio = new FakeStdio();
        $stdio->withInput(json_encode($this->callToolMessage()));

        $this->app->instance(Stdio::class, $stdio);

        Mcp::cli('test-mcp-call', ExampleServer::class);

        $this->artisan('mcp:test-mcp-call');

        $this->assertEquals(
            $this->expectedCallToolResponse(),
            json_decode($stdio->getOutput(), true),
        );
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
}
