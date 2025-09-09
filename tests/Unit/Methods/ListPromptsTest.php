<?php

use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResult;
use Tests\Fixtures\ReviewMyCodePrompt;

it('returns a valid list prompts response', function () {
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

    expect($response)->toBeInstanceOf(JsonRpcResult::class)
        ->and($response->id)->toEqual(1)
        ->and($response->result)->toEqual([
            'prompts' => [
                [
                    'name' => 'review-my-code-prompt',
                    'title' => 'Review My Code Prompt',
                    'description' => 'Instructions for how to review my code',
                    'arguments' => [
                        //
                    ],
                ],
            ],
        ]);
});

it('returns empty list when no prompts registered', function () {
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

    expect($response)->toBeInstanceOf(JsonRpcResult::class);
    expect($response->id)->toEqual(1);
    expect($response->result)->toEqual([
        'prompts' => [],
    ]);
});
