<?php

use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\ReviewMyCodePrompt;

it('returns a valid list prompts response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-prompts',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [],
        prompts: [ReviewMyCodePrompt::class],
    );

    $listPrompts = new ListPrompts;

    $response = $listPrompts->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
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

it('returns empty list when no prompts registered', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-prompts',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [],
        prompts: [],
    );

    $listPrompts = new ListPrompts;

    $response = $listPrompts->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'prompts' => [],
        ]);
});

it('returns empty list when the single prompt is not eligible for registration', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-prompts',
        'params' => [],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [],
        prompts: [new class extends ReviewMyCodePrompt
        {
            public function shouldRegister(): bool
            {
                return false;
            }
        }],
    );

    $listPrompts = new ListPrompts;

    $response = $listPrompts->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'prompts' => [],
        ]);
});

it('returns empty list when the single prompt is not eligible for registration via request', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'list-prompts',
        'params' => [
            'arguments' => ['register_prompts' => false],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 5,
        tools: [],
        resources: [],
        prompts: [new class extends ReviewMyCodePrompt
        {
            public function shouldRegister(Request $request): bool
            {
                return $request->get('register_prompts', true);
            }
        }],
    );

    $listPrompts = new ListPrompts;

    $this->instance('mcp.request', $request->toRequest());
    $response = $listPrompts->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual([
            'prompts' => [],
        ]);
});

it('rejects wrongly typed pagination params', function (array $params, string $message): void {
    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    expect(fn (): mixed => (new ListPrompts)->handle(new JsonRpcRequest(1, 'prompts/list', $params), $context))
        ->toThrow(JsonRpcException::class, $message);
})->with([
    'cursor as int' => [['cursor' => 1], 'The cursor field must be a string.'],
    'cursor as array' => [['cursor' => ['x']], 'The cursor field must be a string.'],
    'per_page as string' => [['per_page' => '10'], 'The per page field must be an integer.'],
    'per_page as zero' => [['per_page' => 0], 'The per page field must be at least 1.'],
    'per_page as negative' => [['per_page' => -1], 'The per page field must be at least 1.'],
]);
