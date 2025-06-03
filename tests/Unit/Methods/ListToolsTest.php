<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (!class_exists('Tests\\Unit\\Methods\\DummyTool1')) {
    for ($i = 1; $i <= 12; $i++) {
        eval("
            namespace Tests\\Unit\\Methods;
            use Laravel\\Mcp\\Contracts\\Tools\\Tool;
            use Laravel\\Mcp\\Tools\\ToolInputSchema;
            class DummyTool{$i} implements Tool {
                public function getName(): string { return 'dummy-tool-{$i}'; }
                public function getDescription(): string { return 'Description for dummy tool {$i}'; }
                public function getInputSchema(ToolInputSchema \$schema): ToolInputSchema { return \$schema; }
                public function call(array \$arguments) { return []; }
            }
        ");
    }
}

class ListToolsTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_list_tools_response()
    {
        $message = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => [],
        ]));

        $context = new SessionContext(
            supportedProtocolVersions: ['2025-03-26'],
            clientCapabilities: [],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: [ExampleTool::class]
        );

        $listTools = new ListTools();

        $response = $listTools->handle($message, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'tools' => [
                [
                    'name' => 'hello-tool',
                    'description' => 'This tool says hello to a person',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The name of the person to greet',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                    'id' => 1,
                ],
            ],
        ], $response->result);
    }

    #[Test]
    public function it_handles_pagination_correctly()
    {
        $toolClasses = [];
        for ($i = 1; $i <= 12; $i++) {
            $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
        }

        $context = new SessionContext(
            supportedProtocolVersions: ['2025-03-26'],
            clientCapabilities: [],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: $toolClasses
        );

        $listTools = new ListTools();

        $firstListToolsMessage = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => [],
        ]));

        $firstPageResponse = $listTools->handle($firstListToolsMessage, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $firstPageResponse);
        $this->assertEquals(1, $firstPageResponse->id);
        $this->assertCount(10, $firstPageResponse->result['tools']);
        $this->assertArrayHasKey('nextCursor', $firstPageResponse->result);
        $this->assertNotNull($firstPageResponse->result['nextCursor']);

        $this->assertEquals('dummy-tool-1', $firstPageResponse->result['tools'][0]['name']);
        $this->assertEquals(1, $firstPageResponse->result['tools'][0]['id']);

        $this->assertEquals('dummy-tool-10', $firstPageResponse->result['tools'][9]['name']);
        $this->assertEquals(10, $firstPageResponse->result['tools'][9]['id']);

        $nextCursor = $firstPageResponse->result['nextCursor'];

        $secondListToolsMessage = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'list-tools',
            'params' => ['cursor' => $nextCursor],
        ]));

        $secondPageResponse = $listTools->handle($secondListToolsMessage, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $secondPageResponse);
        $this->assertEquals(2, $secondPageResponse->id);
        $this->assertCount(2, $secondPageResponse->result['tools']);
        $this->assertArrayNotHasKey('nextCursor', $secondPageResponse->result);

        $this->assertEquals('dummy-tool-11', $secondPageResponse->result['tools'][0]['name']);
        $this->assertEquals(11, $secondPageResponse->result['tools'][0]['id']);

        $this->assertEquals('dummy-tool-12', $secondPageResponse->result['tools'][1]['name']);
        $this->assertEquals(12, $secondPageResponse->result['tools'][1]['id']);
    }
}
