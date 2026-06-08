<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Primitives\Prompt;
use Laravel\Mcp\Client\Schema\PromptResult;
use Laravel\Mcp\Exceptions\ClientException;
use Tests\Fixtures\Client\FakeTransport;

it('returns a collection of prompts keyed by name', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [
                ['name' => 'summarize', 'description' => 'Summarizes text'],
                ['name' => 'translate', 'description' => 'Translates text'],
            ],
        ],
    ]);

    $prompts = (new Client($transport))->prompts();

    expect($prompts)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->and($prompts->keys()->all())->toBe(['summarize', 'translate'])
        ->and($prompts['summarize'])
        ->toBeInstanceOf(Prompt::class)
        ->name->toBe('summarize')
        ->description->toBe('Summarizes text')
        ->and(json_decode($transport->sent[2], true))
        ->toHaveKey('method', 'prompts/list')
        ->not->toHaveKey('params');
});

it('auto-paginates prompts/list until nextCursor is absent', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [['name' => 'first']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'prompts' => [['name' => 'second'], ['name' => 'third']],
        ],
    ]);

    $prompts = (new Client($transport))->prompts();

    expect($prompts->keys()->all())->toBe(['first', 'second', 'third'])
        ->and(json_decode($transport->sent[2], true))->not->toHaveKey('params')
        ->and(json_decode($transport->sent[3], true))->toHaveKey('params.cursor', 'cursor-page-2');
});

it('stops paginating once the prompt limit is reached without fetching the next page', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [['name' => 'a'], ['name' => 'b'], ['name' => 'c']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);

    $prompts = (new Client($transport))->prompts(2);

    expect($prompts->keys()->all())->toBe(['a', 'b'])
        ->and($transport->sent)->toHaveCount(3)
        ->and($transport->responses)->toBeEmpty();
});

it('returns no prompts without connecting when limit is zero', function (): void {
    $transport = new FakeTransport;
    $client = new Client($transport);

    $prompts = $client->prompts(0);

    expect($prompts)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(0)
        ->and($client->connected())->toBeFalse()
        ->and($transport->sent)->toBeEmpty();
});

it('throws when the prompt limit is negative', function (): void {
    $transport = new FakeTransport;

    expect(fn (): Collection => (new Client($transport))->prompts(-1))
        ->toThrow(ClientException::class, 'Prompt list limit must be greater than or equal to zero.')
        ->and($transport->sent)->toBeEmpty();
});

it('throws when prompts/list does not return a prompts array', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => 'not-an-array',
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->prompts())
        ->toThrow(ClientException::class, 'Invalid prompts/list response from server.');
});

it('throws when a prompt payload is not an object', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => ['invalid'],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->prompts())
        ->toThrow(ClientException::class, 'Invalid prompt payload from server.');
});

it('throws when a prompt name is missing or empty', function (array $payload): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [$payload],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->prompts())
        ->toThrow(ClientException::class, 'Invalid prompt payload from server.');
})->with([
    'missing name' => [[]],
    'empty name' => [['name' => '']],
    'blank name' => [['name' => '   ']],
]);

it('throws when a prompt payload has a field of the wrong type', function (array $payload): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [array_merge(['name' => 'summarize'], $payload)],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->prompts())
        ->toThrow(ClientException::class, 'Invalid prompt payload from server.');
})->with([
    'non-string name' => [['name' => 123]],
    'non-string title' => [['title' => 1]],
    'non-string description' => [['description' => []]],
    'non-array arguments' => [['arguments' => 'none']],
    'non-array _meta' => [['_meta' => 'meta']],
]);

it('throws when a server repeats a prompts/list cursor', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [['name' => 'first']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'prompts' => [['name' => 'second']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->prompts())
        ->toThrow(ClientException::class, 'Repeated prompts/list cursor [cursor-page-2] received from server.')
        ->and($transport->sent)->toHaveCount(4);
});

it('throws when prompts/list returns a non-string cursor', function (mixed $nextCursor): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'prompts' => [['name' => 'first']],
            'nextCursor' => $nextCursor,
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->prompts())
        ->toThrow(ClientException::class, 'Invalid prompts/list cursor from server.');
})->with([
    'integer' => [123],
    'array' => [[1, 2, 3]],
]);

it('sends prompts/get by name and concatenates text content', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'description' => 'A greeting prompt',
            'messages' => [
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello, ']],
                ['role' => 'assistant', 'content' => ['type' => 'image', 'data' => 'base64', 'mimeType' => 'image/png']],
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'John!']],
            ],
        ],
    ]);

    $result = (new Client($transport))->getPrompt('greeting', ['name' => 'John']);

    expect($result)
        ->toBeInstanceOf(PromptResult::class)
        ->description->toBe('A greeting prompt')
        ->messages->toHaveCount(3)
        ->and($result->text())->toBe('Hello, John!')
        ->and((string) $result)->toBe('Hello, John!')
        ->and(json_decode($transport->sent[2], true))
        ->toHaveKey('method', 'prompts/get')
        ->toHaveKey('params.name', 'greeting')
        ->toHaveKey('params.arguments', ['name' => 'John']);
});

it('encodes empty prompt arguments as an object on the wire', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['messages' => []],
    ]);

    (new Client($transport))->getPrompt('no-args');

    expect(json_decode($transport->sent[2])->params->arguments)->toBeInstanceOf(stdClass::class);
});

it('skips messages whose content is not an object when extracting text', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'messages' => [
                ['role' => 'user', 'content' => 'plain-string'],
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello!']],
            ],
        ],
    ]);

    $result = (new Client($transport))->getPrompt('greeting');

    expect($result->text())->toBe('Hello!');
});

it('preserves _meta from the prompts/get response', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hi']]],
            '_meta' => ['source' => 'cached', 'duration_ms' => 12],
        ],
    ]);

    $result = (new Client($transport))->getPrompt('greeting');

    expect($result)
        ->meta->toBe(['source' => 'cached', 'duration_ms' => 12]);
});

it('throws when a prompts/get result has a field of the wrong type', function (array $result): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => $result,
    ]);

    expect(fn (): PromptResult => (new Client($transport))->getPrompt('greeting'))
        ->toThrow(ClientException::class, 'Invalid prompts/get result from server.');
})->with([
    'non-array messages' => [['messages' => 'not-an-array']],
    'non-string description' => [['messages' => [], 'description' => []]],
]);
