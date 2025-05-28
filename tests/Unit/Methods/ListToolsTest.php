<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
                ],
            ],
        ], $response->result);
    }
}
