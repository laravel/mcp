<?php

namespace Laravel\Mcp\Tests\Feature;

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\McpServiceProvider;
use Orchestra\Testbench\TestCase;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

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
        Mcp::web('test-mcp', ExampleServer::class);

        $response = $this->postJson('test-mcp', $this->initializeMessage());

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals($this->expectedInitializeResponse(), $responseData);
    }

    #[Test]
    public function it_can_initialize_a_connection_over_stdio()
    {
        // This MCP server is registered in the WorkbenchServiceProvider
        // because Process needs it to be registered outside of the test:
        //
        // Mcp::local('test-mcp', ExampleServer::class);

        $process = new Process(['./vendor/bin/testbench', 'mcp:test-mcp']);
        $process->setInput(json_encode($this->initializeMessage()).PHP_EOL);
        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedInitializeResponse(), $output);
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
}
