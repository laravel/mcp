<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\ListTools;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\Message;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ListToolsTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_list_tools_response()
    {
        $message = new Message(id: 1, params: []);
        $serverContext = new ServerContext(
            capabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: [ExampleTool::class]
        );
        $method = new ListTools();

        $response = $method->handle($message, $serverContext);

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
