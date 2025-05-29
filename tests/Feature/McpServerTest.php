<?php

namespace Laravel\Mcp\Tests\Feature;

use Laravel\Mcp\McpServiceProvider;
use Laravel\Mcp\Session\ArraySessionStore;
use Orchestra\Testbench\TestCase;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;
use Orchestra\Testbench\Attributes\DefineEnvironment;
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

    protected function usesArrayCacheStore($app)
    {
        $app['config']->set('cache.default', 'array');
    }

    #[Test]
    #[DefineEnvironment('usesArrayCacheStore')]
    public function it_can_initialize_a_connection_over_http()
    {
        $response = $this->postJson('test-mcp', $this->initializeMessage());

        $response->assertStatus(200);

        $this->assertEquals($this->expectedInitializeResponse(), $response->json());
    }

    #[Test]
    #[DefineEnvironment('usesArrayCacheStore')]
    public function it_can_list_tools_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->listToolsMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedListToolsResponse(), $response->json());
    }

    #[Test]
    #[DefineEnvironment('usesArrayCacheStore')]
    public function it_can_call_a_tool_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->callToolMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedCallToolResponse(), $response->json());
    }

    #[Test]
    #[DefineEnvironment('usesArrayCacheStore')]
    public function it_can_handle_a_ping_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->pingMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedPingResponse(), $response->json());
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
        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp-initialized']);
        $process->setInput(json_encode($this->listToolsMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedListToolsResponse(), $output);
    }

    #[Test]
    public function it_can_call_a_tool_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp-initialized']);
        $process->setInput(json_encode($this->callToolMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedCallToolResponse(), $output);
    }

    #[Test]
    public function it_can_handle_a_ping_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp-initialized']);
        $process->setInput(json_encode($this->pingMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedPingResponse(), $output);
    }

    private function initializeHttpConnection()
    {
        $response = $this->postJson('test-mcp', $this->initializeMessage());

        $sessionId = $response->headers->get('Mcp-Session-Id');

        $response = $this->postJson('test-mcp', $this->initializeNotificationMessage(), ['Mcp-Session-Id' => $sessionId]);

        return $sessionId;
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
        $server = new ExampleServer(new ArraySessionStore());

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

    private function initializeNotificationMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
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
