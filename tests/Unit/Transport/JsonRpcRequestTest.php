<?php

namespace Laravel\Mcp\Tests\Unit\Transport;

use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonRpcRequestTest extends TestCase
{
    #[Test]
    public function it_can_create_a_message_from_valid_json()
    {
        $json = '{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": {"name": "echo", "arguments": {"message": "Hello, world!"}}}';
        $request = JsonRpcRequest::fromJson($json);

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertEquals(1, $request->id);
        $this->assertEquals('tools/call', $request->method);
        $this->assertEquals(['name' => 'echo', 'arguments' => ['message' => 'Hello, world!']], $request->params);
    }

    #[Test]
    public function it_can_create_a_notification_message_from_valid_json()
    {
        $json = '{"jsonrpc": "2.0", "method": "notifications/initialized"}';
        $request = JsonRpcRequest::fromJson($json);

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertNull($request->id);
        $this->assertEquals('notifications/initialized', $request->method);
        $this->assertEquals([], $request->params);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Parse error');
        $this->expectExceptionCode(-32700);

        JsonRpcRequest::fromJson('invalid_json');
    }

    #[Test]
    public function it_throws_exception_for_missing_jsonrpc_version()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
        $this->expectExceptionCode(-32600);

        JsonRpcRequest::fromJson('{"id": 1, "method": "initialize"}');
    }

    #[Test]
    public function it_throws_exception_for_incorrect_jsonrpc_version()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid JSON-RPC version. Must be "2.0".');
        $this->expectExceptionCode(-32600);

        JsonRpcRequest::fromJson('{"jsonrpc": "1.0", "id": 1, "method": "initialize"}');
    }

    #[Test]
    public function it_throws_exception_for_invalid_id_type()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid params: "id" must be an integer or null if present.');
        $this->expectExceptionCode(-32602);

        JsonRpcRequest::fromJson('{"jsonrpc": "2.0", "id": "not-an-integer", "method": "initialize"}');
    }

    #[Test]
    public function it_throws_exception_for_missing_method()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
        $this->expectExceptionCode(-32600);

        JsonRpcRequest::fromJson('{"jsonrpc": "2.0", "id": 1}');
    }

    #[Test]
    public function it_throws_exception_for_non_string_method()
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Invalid Request: Invalid or missing "method". Must be a string.');
        $this->expectExceptionCode(-32600);

        JsonRpcRequest::fromJson('{"jsonrpc": "2.0", "id": 1, "method": 123}');
    }
}
