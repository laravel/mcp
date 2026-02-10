<?php

use Laravel\Mcp\Client\ClientContext;
use Laravel\Mcp\Client\Methods\CallTool;
use Tests\Fixtures\FakeClientTransport;

it('sends tool name and arguments correctly', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'content' => [['type' => 'text', 'text' => 'Hello, World!']],
                'isError' => false,
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new CallTool;

    $handler->handle($context, ['name' => 'say-hi', 'arguments' => ['name' => 'World']]);

    $sent = $transport->sentMessages();
    $request = json_decode($sent[0], true);

    expect($request['method'])->toBe('tools/call')
        ->and($request['params']['name'])->toBe('say-hi')
        ->and($request['params']['arguments'])->toBe(['name' => 'World']);
});

it('returns result content', function (): void {
    $transport = new FakeClientTransport([
        json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'content' => [['type' => 'text', 'text' => 'Result text']],
                'isError' => false,
            ],
        ]),
    ]);

    $transport->connect();

    $context = new ClientContext($transport, 'test-client');
    $handler = new CallTool;

    $result = $handler->handle($context, ['name' => 'test-tool', 'arguments' => []]);

    expect($result)->toHaveKey('content')
        ->and($result['content'][0]['text'])->toBe('Result text')
        ->and($result['isError'])->toBeFalse();
});
