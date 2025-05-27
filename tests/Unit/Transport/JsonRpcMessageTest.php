<?php

namespace Laravel\Mcp\Tests\Unit\Transport;

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Transport\JsonRpcMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonRpcMessageTest extends TestCase
{
    #[Test]
    public function it_can_create_a_message_from_valid_json()
    {
        $json = '{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": {"name": "echo", "arguments": {"message": "Hello, world!"}}}';
        $message = JsonRpcMessage::fromJson($json);

        $this->assertInstanceOf(JsonRpcMessage::class, $message);
        $this->assertEquals(1, $message->id);
        $this->assertEquals('tools/call', $message->method);
        $this->assertEquals(['name' => 'echo', 'arguments' => ['message' => 'Hello, world!']], $message->params);
    }

    #[Test]
    public function it_can_create_a_notification_message_from_valid_json()
    {
        $json = '{"jsonrpc": "2.0", "method": "notifications/initialized"}';
        $message = JsonRpcMessage::fromJson($json);

        $this->assertInstanceOf(JsonRpcMessage::class, $message);
        $this->assertNull($message->id);
        $this->assertEquals('notifications/initialized', $message->method);
        $this->assertEquals([], $message->params);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Parse error');
        $this->expectExceptionCode(-32700);

        JsonRpcMessage::fromJson('invalid_json');
    }

    #[Test]
    public function it_throws_exception_for_missing_jsonrpc_version()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
        $this->expectExceptionCode(-32600);

        JsonRpcMessage::fromJson('{"id": 1, "method": "initialize"}');
    }

    #[Test]
    public function it_throws_exception_for_incorrect_jsonrpc_version()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
        $this->expectExceptionCode(-32600);

        JsonRpcMessage::fromJson('{"jsonrpc": "1.0", "id": 1, "method": "initialize"}');
    }

    #[Test]
    public function it_throws_exception_for_invalid_id_type()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid params: "id" must be an integer or null if present.');
        $this->expectExceptionCode(-32602);

        JsonRpcMessage::fromJson('{"jsonrpc": "2.0", "id": "not-an-integer", "method": "initialize"}');
    }

    #[Test]
    public function it_throws_exception_for_missing_method()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
        $this->expectExceptionCode(-32600);

        JsonRpcMessage::fromJson('{"jsonrpc": "2.0", "id": 1}');
    }

    #[Test]
    public function it_throws_exception_for_non_string_method()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
        $this->expectExceptionCode(-32600);

        JsonRpcMessage::fromJson('{"jsonrpc": "2.0", "id": 1, "method": 123}');
    }
}
