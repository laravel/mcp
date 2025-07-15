<?php

namespace Tests\Unit\Methods;

use Illuminate\Support\ItemNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Tests\Fixtures\ReviewMyCodePrompt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GetPromptTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_get_prompt_response()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'review-my-code-prompt',
                'arguments' => [],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [],
            resources: [],
            prompts: [ReviewMyCodePrompt::class],
        );

        $method = new GetPrompt;

        $response = $method->handle($request, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'description' => 'Instructions for how to review my code',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Here are the instructions on how to review my code',
                    ],
                ],
            ],
        ], $response->result);
    }

    #[Test]
    public function it_throws_exception_when_name_parameter_is_missing()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [
                'arguments' => [],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [],
            resources: [],
            prompts: [ReviewMyCodePrompt::class],
        );

        $method = new GetPrompt;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameter: name');

        $method->handle($request, $context);
    }

    #[Test]
    public function it_throws_exception_when_prompt_not_found()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'non-existent-prompt',
                'arguments' => [],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [],
            resources: [],
            prompts: [ReviewMyCodePrompt::class],
        );

        $method = new GetPrompt;

        $this->expectException(ItemNotFoundException::class);
        $this->expectExceptionMessage('Prompt not found');

        $method->handle($request, $context);
    }

    #[Test]
    public function it_passes_arguments_to_prompt_handler()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'review-my-code-prompt',
                'arguments' => ['test_arg' => 'test_value'],
            ],
        ]));

        $context = new ServerContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: [],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test instructions',
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
            tools: [],
            resources: [],
            prompts: [ReviewMyCodePrompt::class],
        );

        $method = new GetPrompt;

        $response = $method->handle($request, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertArrayHasKey('description', $response->result);
        $this->assertArrayHasKey('messages', $response->result);
    }
}
