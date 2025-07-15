<?php

namespace Laravel\Mcp\Tests\Unit\Transport;

use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonRpcResponseTest extends TestCase
{
    #[Test]
    public function it_can_return_response_as_array()
    {
        $response = JsonRpcResponse::create(1, ['foo' => 'bar']);

        $expectedArray = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['foo' => 'bar'],
        ];

        $this->assertEquals($expectedArray, $response->toArray());
    }

    #[Test]
    public function it_can_return_response_as_json()
    {
        $response = JsonRpcResponse::create(1, ['foo' => 'bar']);

        $expectedJson = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['foo' => 'bar'],
        ]);

        $this->assertEquals($expectedJson, $response->toJson());
    }

    #[Test]
    public function it_converts_empty_array_result_to_object()
    {
        $response = JsonRpcResponse::create(1, []);

        $expectedJson = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => (object) [],
        ]);

        $this->assertEquals($expectedJson, $response->toJson());
    }
}
