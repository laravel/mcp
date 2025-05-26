<?php

namespace Laravel\Mcp\Tests\Unit\Transport;

use Laravel\Mcp\Transport\JsonRpcResponse;
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
}
