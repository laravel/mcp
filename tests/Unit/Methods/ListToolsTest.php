<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (! class_exists('Tests\\Unit\\Methods\\DummyTool1')) {
    for ($i = 1; $i <= 12; $i++) {
        eval("
            namespace Tests\\Unit\\Methods;
            use Generator;
            use Laravel\\Mcp\\Server\\Tool;
            use Laravel\\Mcp\\Server\\Tools\\ToolResult;
            use Laravel\\Mcp\\Server\\Tools\\ToolInputSchema;
            class DummyTool{$i} extends Tool {
                public function description(): string { return 'Description for dummy tool {$i}'; }
                public function schema(ToolInputSchema \$schema): ToolInputSchema { return \$schema; }
                public function handle(array \$arguments): ToolResult|Generator { return []; }
            }
        ");
    }
}

class ListToolsTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_list_tools_response()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => [],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 5,
            tools: [ExampleTool::class],
            resources: [],
            prompts: [],
        );

        $listTools = new ListTools;

        $response = $listTools->handle($request, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'tools' => [
                [
                    'name' => 'example-tool',
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
                    'annotations' => (object) [],
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

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: $toolClasses,
            resources: [],
            prompts: [],
        );

        $listTools = new ListTools;

        $firstListToolsRequest = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => [],
        ]));

        $firstPageResponse = $listTools->handle($firstListToolsRequest, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $firstPageResponse);
        $this->assertEquals(1, $firstPageResponse->id);
        $this->assertCount(10, $firstPageResponse->result['tools']);
        $this->assertArrayHasKey('nextCursor', $firstPageResponse->result);
        $this->assertNotNull($firstPageResponse->result['nextCursor']);

        $this->assertEquals('dummy-tool1', $firstPageResponse->result['tools'][0]['name']);

        $this->assertEquals('dummy-tool10', $firstPageResponse->result['tools'][9]['name']);

        $nextCursor = $firstPageResponse->result['nextCursor'];

        $secondListToolsRequest = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'list-tools',
            'params' => ['cursor' => $nextCursor],
        ]));

        $secondPageResponse = $listTools->handle($secondListToolsRequest, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $secondPageResponse);
        $this->assertEquals(2, $secondPageResponse->id);
        $this->assertCount(2, $secondPageResponse->result['tools']);
        $this->assertArrayNotHasKey('nextCursor', $secondPageResponse->result);

        $this->assertEquals('dummy-tool11', $secondPageResponse->result['tools'][0]['name']);

        $this->assertEquals('dummy-tool12', $secondPageResponse->result['tools'][1]['name']);
    }

    #[Test]
    public function it_uses_default_per_page_when_not_provided()
    {
        $toolClasses = [];
        for ($i = 1; $i <= 12; $i++) {
            $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
        }

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 7,
            tools: $toolClasses,
            resources: [],
            prompts: [],
        );

        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => [/** no per_page */],
        ]));

        $listTools = new ListTools;
        $response = $listTools->handle($request, $context);

        $this->assertCount(7, $response->result['tools']);
        $this->assertArrayHasKey('nextCursor', $response->result);
    }

    #[Test]
    public function it_uses_requested_per_page_when_valid()
    {
        $toolClasses = [];
        for ($i = 1; $i <= 12; $i++) {
            $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
        }

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: $toolClasses,
            resources: [],
            prompts: [],
        );

        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => ['per_page' => 5],
        ]));

        $listTools = new ListTools;
        $response = $listTools->handle($request, $context);

        $this->assertCount(5, $response->result['tools']);
        $this->assertArrayHasKey('nextCursor', $response->result);
    }

    #[Test]
    public function it_caps_per_page_at_max_pagination_length()
    {
        $toolClasses = [];
        for ($i = 1; $i <= 12; $i++) {
            $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
        }

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 7,
            defaultPaginationLength: 7,
            tools: $toolClasses,
            resources: [],
            prompts: [],
        );

        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => ['per_page' => 20],
        ]));

        $listTools = new ListTools;
        $response = $listTools->handle($request, $context);

        $this->assertCount(7, $response->result['tools']);
        $this->assertArrayHasKey('nextCursor', $response->result);
    }

    #[Test]
    public function it_respects_per_page_when_bigger_than_default()
    {
        $toolClasses = [];
        for ($i = 1; $i <= 12; $i++) {
            $toolClasses[] = "Tests\\Unit\\Methods\\DummyTool{$i}";
        }

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 15,
            defaultPaginationLength: 5,
            tools: $toolClasses,
            resources: [],
            prompts: [],
        );

        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-tools',
            'params' => ['per_page' => 8],
        ]));

        $listTools = new ListTools;
        $response = $listTools->handle($request, $context);

        $this->assertCount(8, $response->result['tools']);
        $this->assertArrayHasKey('nextCursor', $response->result);
    }
}
