<?php

namespace Tests\Unit\Methods;

use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Tests\Fixtures\ReviewMyCodePrompt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ListPromptsTest extends TestCase
{
    #[Test]
    public function it_returns_a_valid_list_prompts_response()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-prompts',
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
            tools: [],
            resources: [],
            prompts: [ReviewMyCodePrompt::class],
        );

        $listPrompts = new ListPrompts;

        $response = $listPrompts->handle($request, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'prompts' => [
                [
                    'name' => 'review-my-code-prompt',
                    'title' => 'Review My Code Prompt',
                    'description' => 'Instructions for how to review my code',
                    'arguments' => [
                        [
                            'name' => 'best_cheese',
                            'description' => 'The best cheese',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ], $response->result);
    }

    #[Test]
    public function it_returns_empty_list_when_no_prompts_registered()
    {
        $request = JsonRpcRequest::fromJson(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'list-prompts',
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
            tools: [],
            resources: [],
            prompts: [],
        );

        $listPrompts = new ListPrompts;

        $response = $listPrompts->handle($request, $context);

        $this->assertInstanceOf(JsonRpcResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals([
            'prompts' => [],
        ], $response->result);
    }
}
