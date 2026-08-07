<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Exceptions\ClientException;
use Tests\Fixtures\Client\FakeTransport;

function inputRequiredResponse(int $id, string $state = 'opaque-state'): string
{
    return (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'resultType' => 'input_required',
            'inputRequests' => [
                'who' => [
                    'method' => 'elicitation/create',
                    'params' => ['mode' => 'form', 'message' => 'Who are you?'],
                ],
            ],
            'requestState' => $state,
        ],
    ]);
}

function toolCallResponse(int $id, string $text): string
{
    return (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'resultType' => 'complete',
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
        ],
    ]);
}

it('retries the request with the gathered input and the echoed state', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = inputRequiredResponse(2);
    $transport->responses[] = toolCallResponse(3, 'Hello, John');

    $result = (new Client($transport))
        ->onElicitation(fn (array $params): array => ['action' => 'accept', 'content' => ['name' => 'John']])
        ->callTool('say-hi');

    expect($result->text())->toBe('Hello, John');

    $first = json_decode($transport->sent[1], true);
    $retry = json_decode($transport->sent[2], true);

    expect($first['params'])->not->toHaveKey('inputResponses');
    expect($retry['method'])->toBe('tools/call');
    expect($retry['id'])->toBe(3)->not->toBe($first['id']);
    expect($retry['params']['requestState'])->toBe('opaque-state');
    expect($retry['params']['inputResponses']['who'])->toBe(['action' => 'accept', 'content' => ['name' => 'John']]);
});

it('declares only the capabilities it has handlers for', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolCallResponse(2, 'hi');

    (new Client($transport))
        ->onElicitation(fn (array $params): array => [])
        ->onListRoots(fn (array $params): array => ['roots' => []])
        ->callTool('say-hi');

    $capabilities = json_decode($transport->sent[1], true)['params']['_meta']['io.modelcontextprotocol/clientCapabilities'];

    expect($capabilities)->toBe(['elicitation' => [], 'roots' => []]);
});

it('fails when the server asks for input it has no handler for', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = inputRequiredResponse(2);

    expect(fn (): mixed => (new Client($transport))->callTool('say-hi'))
        ->toThrow(ClientException::class, 'The server requested [elicitation/create], which this client has no handler for.');
});

it('gives up when the server keeps asking for input', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();

    foreach (range(2, 8) as $id) {
        $transport->responses[] = inputRequiredResponse($id);
    }

    $client = (new Client($transport))->onElicitation(fn (array $params): array => []);

    expect(fn (): mixed => $client->callTool('say-hi'))
        ->toThrow(ClientException::class, 'asked for input more than 5 times');
});

it('omits the request state when the server sent none', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['resultType' => 'input_required', 'requestState' => null, 'inputRequests' => []],
    ]);
    $transport->responses[] = toolCallResponse(3, 'hi');

    (new Client($transport))->callTool('say-hi');

    expect(json_decode($transport->sent[2], true)['params'])
        ->not->toHaveKey('requestState')
        ->not->toHaveKey('inputResponses');
});
