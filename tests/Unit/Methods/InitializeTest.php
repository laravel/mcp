<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Methods\Initialize;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Laravel\Mcp\Exceptions\JsonRpcException;

class InitializeTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_initialize_response()
    {
        $message = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]));

        $serverContext = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
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

    #[Test]
    public function it_throws_exception_for_unsupported_protocol_version()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Unsupported protocol version');
        $this->expectExceptionCode(-32602);

        $message = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
            ],
        ]));

        $serverContext = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            capabilities: ['listChanged' => false],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            tools: []
        );

        $method = new Initialize();

        try {
            $method->handle($message, $serverContext);
        } catch (JsonRpcException $e) {
            $this->assertEquals(1, $e->getRequestId());
            $this->assertEquals([
                'supported' => ['2025-03-26'],
                'requested' => '2024-11-05',
            ], $e->getData());
            throw $e;
        }
    }

    #[Test]
    public function it_uses_requested_protocol_version_if_supported()
    {
        $requestedVersion = '2024-11-05';
        $message = JsonRpcMessage::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => $requestedVersion,
            ],
        ]));

        $serverContext = new ServerContext(
            supportedProtocolVersions: ['2025-03-26', '2024-11-05'],
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
        $this->assertEquals($requestedVersion, $response->result['protocolVersion']);
    }
}
