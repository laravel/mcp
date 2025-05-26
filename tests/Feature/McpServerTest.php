<?php

namespace Laravel\Mcp\Tests\Feature;

use Laravel\Mcp\McpServiceProvider;
use Orchestra\Testbench\TestCase;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Workbench\App\Providers\WorkbenchServiceProvider;

class McpServerTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,

            // MCP servers used in this test are defined in the service provider
            WorkbenchServiceProvider::class,
        ];
    }

    #[Test]
    public function it_can_initialize_a_connection_over_http()
    {
        $response = $this->postJson('test-mcp', $this->initializeMessage());

        $response->assertStatus(200);

        $this->assertEquals($this->expectedInitializeResponse(), $response->json());
    }

    #[Test]
    public function it_can_list_tools_over_http()
    {
        $response = $this->postJson('test-mcp', $this->listToolsMessage());

        $response->assertStatus(200);

        $this->assertEquals($this->expectedListToolsResponse(), $response->json());
    }

    #[Test]
    public function it_can_call_a_tool_over_http()
    {
        $response = $this->postJson('test-mcp', $this->callToolMessage());

        $response->assertStatus(200);

        $this->assertEquals($this->expectedCallToolResponse(), $response->json());
    }

    #[Test]
    public function it_can_initialize_a_connection_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp']);
        $process->setInput(json_encode($this->initializeMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedInitializeResponse(), $output);
    }

    #[Test]
    public function it_can_list_tools_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp']);
        $process->setInput(json_encode($this->listToolsMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedListToolsResponse(), $output);
    }

    #[Test]
    public function it_can_call_a_tool_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp']);
        $process->setInput(json_encode($this->callToolMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedCallToolResponse(), $output);
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
