<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Exceptions\ClientException;
use Tests\Fixtures\Client\FakeTransport;

it('returns a collection of tools keyed by name', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [
                ['name' => 'add', 'description' => 'Adds two numbers'],
                ['name' => 'subtract', 'description' => 'Subtracts two numbers'],
            ],
        ],
    ]);

    $tools = (new Client($transport))->tools();

    expect($tools)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->and($tools->keys()->all())->toBe(['add', 'subtract'])
        ->and($tools['add'])
        ->toBeInstanceOf(Tool::class)
        ->name->toBe('add')
        ->description->toBe('Adds two numbers')
        ->and(json_decode($transport->sent[2], true))
        ->toHaveKey('method', 'tools/list')
        ->not->toHaveKey('params');
});

it('auto-paginates tools/list until nextCursor is absent', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [['name' => 'first']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'tools' => [['name' => 'second'], ['name' => 'third']],
        ],
    ]);

    $tools = (new Client($transport))->tools();

    expect($tools->keys()->all())->toBe(['first', 'second', 'third'])
        ->and(json_decode($transport->sent[2], true))->not->toHaveKey('params')
        ->and(json_decode($transport->sent[3], true))->toHaveKey('params.cursor', 'cursor-page-2');
});

it('stops paginating once limit is reached without fetching the next page', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [['name' => 'a'], ['name' => 'b'], ['name' => 'c']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);

    $tools = (new Client($transport))->tools(2);

    expect($tools->keys()->all())->toBe(['a', 'b'])
        ->and($transport->sent)->toHaveCount(3)
        ->and($transport->responses)->toBeEmpty();
});

it('returns no tools without connecting when limit is zero', function (): void {
    $transport = new FakeTransport;
    $client = new Client($transport);

    $tools = $client->tools(0);

    expect($tools)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(0)
        ->and($client->connected())->toBeFalse()
        ->and($transport->sent)->toBeEmpty();
});

it('throws when the tool limit is negative', function (): void {
    $transport = new FakeTransport;

    expect(fn (): Collection => (new Client($transport))->tools(-1))
        ->toThrow(ClientException::class, 'Tool list limit must be greater than or equal to zero.')
        ->and($transport->sent)->toBeEmpty();
});

it('throws when tools/list does not return a tools array', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => 'not-an-array',
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->tools())
        ->toThrow(ClientException::class, 'Invalid tools/list response from server.');
});

it('throws when a tool payload is not an object', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => ['invalid'],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->tools())
        ->toThrow(ClientException::class, 'Invalid tool payload from server.');
});

it('throws when a tool name is missing or empty', function (array $payload): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [$payload],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->tools())
        ->toThrow(ClientException::class, 'Invalid tool payload from server.');
})->with([
    'missing name' => [[]],
    'empty name' => [['name' => '']],
    'blank name' => [['name' => '   ']],
]);

it('throws when a tool payload has a field of the wrong type', function (array $payload): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [array_merge(['name' => 'add'], $payload)],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->tools())
        ->toThrow(ClientException::class, 'Invalid tool payload from server.');
})->with([
    'non-string name' => [['name' => 123]],
    'non-string title' => [['title' => 1]],
    'non-string description' => [['description' => []]],
    'non-array inputSchema' => [['inputSchema' => 'object']],
    'non-array outputSchema' => [['outputSchema' => 'object']],
    'non-array annotations' => [['annotations' => 'none']],
    'non-array _meta' => [['_meta' => 'meta']],
]);

it('throws when a server repeats a tools/list cursor', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [['name' => 'first']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'tools' => [['name' => 'second']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->tools())
        ->toThrow(ClientException::class, 'Repeated tools/list cursor [cursor-page-2] received from server.')
        ->and($transport->sent)->toHaveCount(4);
});

it('calls a tool fluently via $tool->call() and returns a ToolResult', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'tools' => [['name' => 'say-hi', 'description' => 'Greets a person']],
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'content' => [['type' => 'text', 'text' => 'Hello, John!']],
            'isError' => false,
        ],
    ]);

    $result = (new Client($transport))->tools()['say-hi']->call(['name' => 'John']);

    expect($result)
        ->toBeInstanceOf(ToolResult::class)
        ->and($result->text())->toBe('Hello, John!')
        ->and(json_decode($transport->sent[3], true))
        ->toHaveKey('method', 'tools/call')
        ->toHaveKey('params.name', 'say-hi')
        ->toHaveKey('params.arguments', ['name' => 'John']);
});

it('sends tools/call by name and concatenates text content', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'content' => [
                ['type' => 'text', 'text' => 'Hello, '],
                ['type' => 'image', 'data' => 'base64', 'mimeType' => 'image/png'],
                ['type' => 'text', 'text' => 'John!'],
            ],
            'isError' => false,
        ],
    ]);

    $result = (new Client($transport))->callTool('say-hi', ['name' => 'John']);

    expect($result)
        ->toBeInstanceOf(ToolResult::class)
        ->isError->toBeFalse()
        ->content->toHaveCount(3)
        ->and($result->text())->toBe('Hello, John!')
        ->and((string) $result)->toBe('Hello, John!')
        ->and(json_decode($transport->sent[2], true))
        ->toHaveKey('method', 'tools/call')
        ->toHaveKey('params.name', 'say-hi')
        ->toHaveKey('params.arguments', ['name' => 'John']);
});

it('encodes empty arguments as an object on the wire', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['content' => [], 'isError' => false],
    ]);

    (new Client($transport))->callTool('no-args');

    expect(json_decode($transport->sent[2])->params->arguments)->toBeInstanceOf(stdClass::class);
});

it('preserves structuredContent and _meta from the server response', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'content' => [['type' => 'text', 'text' => '{"temp": 72}']],
            'structuredContent' => ['temp' => 72, 'conditions' => 'sunny'],
            'isError' => false,
            '_meta' => ['source' => 'cached', 'duration_ms' => 12],
        ],
    ]);

    $result = (new Client($transport))->callTool('weather', ['city' => 'NYC']);

    expect($result)
        ->structuredContent->toBe(['temp' => 72, 'conditions' => 'sunny'])
        ->meta->toBe(['source' => 'cached', 'duration_ms' => 12]);
});

it('surfaces tool-level errors as ToolResult::isError true', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'content' => [['type' => 'text', 'text' => 'Validation failed.']],
            'isError' => true,
        ],
    ]);

    $result = (new Client($transport))->callTool('say-hi');

    expect($result)
        ->isError->toBeTrue()
        ->and($result->text())->toBe('Validation failed.');
});

it('throws when a tools/call result has a field of the wrong type', function (array $result): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => $result,
    ]);

    expect(fn (): ToolResult => (new Client($transport))->callTool('say-hi'))
        ->toThrow(ClientException::class, 'Invalid tools/call result from server.');
})->with([
    'non-array content' => [['content' => 'not-an-array']],
    'non-bool isError' => [['content' => [], 'isError' => 'true']],
]);
