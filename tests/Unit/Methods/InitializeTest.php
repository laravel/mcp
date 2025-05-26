<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InitializeTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_initialize_response()
    {
        $message = new Message(id: 1, params: []);
        $serverContext = new ServerContext(
            capabilities: ['listChanged' => false],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: []
        );
        $method = new Initialize();

        $response = $method->handle($message, $serverContext);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'protocolVersion' => '2025-03-26',
            'capabilities' => ['listChanged' => false],
            'serverInfo' => [
                'name' => 'Test Server',
                'version' => '1.0.0',
            ],
            'instructions' => 'Test instructions',
        ], $response->result);
    }
}
