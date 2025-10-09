<?php

use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Tests\Fixtures\ReviewMyCodePrompt;
use Tests\Fixtures\TellMeHiPrompt;

it('returns a valid get prompt response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'review-my-code-prompt',
            'arguments' => [],
        ],
    ]);

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
        resourceTemplates: [],
        prompts: [ReviewMyCodePrompt::class],
    );

    $method = new GetPrompt;

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1);
    expect($payload['result'])->toEqual([
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
    ]);
});

it('resolves the handle method from the IOC container', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'tell-me-hi-prompt',
            'arguments' => [],
        ],
    ]);

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
        resourceTemplates: [],
        prompts: [TellMeHiPrompt::class],
    );

    $method = new GetPrompt;

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1);
    expect($payload['result'])->toEqual([
        'description' => 'Instructions for how too tell me hi',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => 'Here are the instructions on how to tell me hi',
                ],
            ],
        ],
    ]);
});
