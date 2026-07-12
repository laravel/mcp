<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Primitives\Resource;
use Laravel\Mcp\Client\Schema\ResourceReadResult;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Exceptions\ClientException;
use Tests\Fixtures\Client\FakeTransport;

it('returns a collection of resources keyed by uri', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [
                ['uri' => 'file://readme', 'name' => 'readme', 'description' => 'The readme', 'mimeType' => 'text/plain', 'size' => 1024],
                ['uri' => 'file://license', 'name' => 'license'],
            ],
        ],
    ]);

    $resources = (new Client($transport))->resources();

    expect($resources)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->and($resources->keys()->all())->toBe(['file://readme', 'file://license'])
        ->and($resources['file://readme'])
        ->toBeInstanceOf(Resource::class)
        ->uri->toBe('file://readme')
        ->name->toBe('readme')
        ->description->toBe('The readme')
        ->mimeType->toBe('text/plain')
        ->size->toBe(1024)
        ->and($resources['file://license'])
        ->title->toBeNull()
        ->description->toBeNull()
        ->mimeType->toBeNull()
        ->size->toBeNull()
        ->and(json_decode($transport->sent[2], true))
        ->toHaveKey('method', 'resources/list')
        ->not->toHaveKey('params');
});

it('auto-paginates resources/list until nextCursor is absent', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [['uri' => 'file://first', 'name' => 'first']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'resources' => [
                ['uri' => 'file://second', 'name' => 'second'],
                ['uri' => 'file://third', 'name' => 'third'],
            ],
        ],
    ]);

    $resources = (new Client($transport))->resources();

    expect($resources->keys()->all())->toBe(['file://first', 'file://second', 'file://third'])
        ->and(json_decode($transport->sent[2], true))->not->toHaveKey('params')
        ->and(json_decode($transport->sent[3], true))->toHaveKey('params.cursor', 'cursor-page-2');
});

it('stops paginating once the resource limit is reached without fetching the next page', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [
                ['uri' => 'file://a', 'name' => 'a'],
                ['uri' => 'file://b', 'name' => 'b'],
                ['uri' => 'file://c', 'name' => 'c'],
            ],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);

    $resources = (new Client($transport))->resources(2);

    expect($resources->keys()->all())->toBe(['file://a', 'file://b'])
        ->and($transport->sent)->toHaveCount(3)
        ->and($transport->responses)->toBeEmpty();
});

it('returns no resources without connecting when limit is zero', function (): void {
    $transport = new FakeTransport;
    $client = new Client($transport);

    $resources = $client->resources(0);

    expect($resources)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(0)
        ->and($client->connected())->toBeFalse()
        ->and($transport->sent)->toBeEmpty();
});

it('throws when the resource limit is negative', function (): void {
    $transport = new FakeTransport;

    expect(fn (): Collection => (new Client($transport))->resources(-1))
        ->toThrow(ClientException::class, 'Resource list limit must be greater than or equal to zero.')
        ->and($transport->sent)->toBeEmpty();
});

it('throws when resources/list does not return a resources array', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => 'not-an-array',
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->resources())
        ->toThrow(ClientException::class, 'Invalid resources/list response from server.');
});

it('throws when a resource payload is not an object', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => ['invalid'],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->resources())
        ->toThrow(ClientException::class, 'Invalid resource payload from server.');
});

it('throws when a resource payload is missing a uri or name', function (array $payload): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [$payload],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->resources())
        ->toThrow(ClientException::class, 'Invalid resource payload from server.');
})->with([
    'missing uri' => [['name' => 'readme']],
    'empty uri' => [['uri' => '', 'name' => 'readme']],
    'blank uri' => [['uri' => '   ', 'name' => 'readme']],
    'missing name' => [['uri' => 'file://readme']],
    'empty name' => [['uri' => 'file://readme', 'name' => '']],
    'blank name' => [['uri' => 'file://readme', 'name' => '   ']],
]);

it('throws when a resource payload has a field of the wrong type', function (array $payload): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [array_merge(['uri' => 'file://readme', 'name' => 'readme'], $payload)],
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->resources())
        ->toThrow(ClientException::class, 'Invalid resource payload from server.');
})->with([
    'non-string title' => [['title' => 1]],
    'non-string description' => [['description' => []]],
    'non-string mimeType' => [['mimeType' => 1]],
    'non-int size' => [['size' => 'big']],
    'non-array annotations' => [['annotations' => 'none']],
    'non-array _meta' => [['_meta' => 'meta']],
]);

it('preserves title, annotations and _meta on a resource', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [[
                'uri' => 'file://readme',
                'name' => 'readme',
                'title' => 'Readme',
                'annotations' => ['audience' => ['user']],
                '_meta' => ['source' => 'cached'],
            ]],
        ],
    ]);

    $resource = (new Client($transport))->resources()['file://readme'];

    expect($resource)
        ->title->toBe('Readme')
        ->annotations->toBe(['audience' => ['user']])
        ->meta->toBe(['source' => 'cached']);
});

it('throws when a server repeats a resources/list cursor', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [['uri' => 'file://first', 'name' => 'first']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => [
            'resources' => [['uri' => 'file://second', 'name' => 'second']],
            'nextCursor' => 'cursor-page-2',
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->resources())
        ->toThrow(ClientException::class, 'Repeated resources/list cursor [cursor-page-2] received from server.')
        ->and($transport->sent)->toHaveCount(4);
});

it('throws when resources/list returns a non-string cursor', function (mixed $nextCursor): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'resources' => [['uri' => 'file://first', 'name' => 'first']],
            'nextCursor' => $nextCursor,
        ],
    ]);

    expect(fn (): Collection => (new Client($transport))->resources())
        ->toThrow(ClientException::class, 'Invalid resources/list cursor from server.');
})->with([
    'integer' => [123],
    'array' => [[1, 2, 3]],
]);

it('returns the given default when authorization is required for resources', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 401),
    ]);

    $resources = (new Client(new HttpTransport('https://mcp.test/mcp')))->resources(default: []);

    expect($resources)->toBeInstanceOf(Collection::class)->toBeEmpty();
});

it('rethrows the authorization exception for resources when no default is given', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 401),
    ]);

    expect(fn (): Collection => (new Client(new HttpTransport('https://mcp.test/mcp')))->resources())
        ->toThrow(AuthorizationRequiredException::class);
});

it('sends resources/read by uri and returns text content', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => [
                ['uri' => 'file://readme', 'mimeType' => 'text/plain', 'text' => 'Hello, '],
                ['uri' => 'file://readme', 'mimeType' => 'text/plain', 'text' => 'world!'],
            ],
        ],
    ]);

    $result = (new Client($transport))->readResource('file://readme');

    expect($result)
        ->toBeInstanceOf(ResourceReadResult::class)
        ->contents->toHaveCount(2)
        ->and($result->content())->toBe('Hello, world!')
        ->and((string) $result)->toBe('Hello, world!')
        ->and(json_decode($transport->sent[2], true))
        ->toHaveKey('method', 'resources/read')
        ->toHaveKey('params.uri', 'file://readme');
});

it('decodes blob content from a resources/read result', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => [
                ['uri' => 'file://logo', 'mimeType' => 'image/png', 'blob' => base64_encode('binary-')],
                ['uri' => 'file://logo', 'mimeType' => 'image/png', 'blob' => 'not-valid-base64!!'],
                ['uri' => 'file://logo', 'mimeType' => 'image/png', 'blob' => 123],
                ['uri' => 'file://logo', 'mimeType' => 'image/png', 'blob' => base64_encode('data')],
            ],
        ],
    ]);

    $result = (new Client($transport))->readResource('file://logo');

    expect($result->content())->toBe('binary-data');
});

it('returns the mime type from the first content item', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => [
                ['uri' => 'file://logo', 'mimeType' => 'image/png', 'blob' => base64_encode('data')],
            ],
        ],
    ]);

    $result = (new Client($transport))->readResource('file://logo');

    expect($result->mimeType())->toBe('image/png');
});

it('returns null mime type when contents are empty', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['contents' => []],
    ]);

    $result = (new Client($transport))->readResource('file://empty');

    expect($result->mimeType())->toBeNull();
});

it('preserves _meta from the resources/read response', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => [['uri' => 'file://readme', 'text' => 'Hi']],
            '_meta' => ['source' => 'cached', 'duration_ms' => 12],
        ],
    ]);

    $result = (new Client($transport))->readResource('file://readme');

    expect($result)
        ->meta->toBe(['source' => 'cached', 'duration_ms' => 12]);
});

it('throws when a resources/read result has a non-array contents field', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => 'not-an-array',
        ],
    ]);

    expect(fn (): ResourceReadResult => (new Client($transport))->readResource('file://readme'))
        ->toThrow(ClientException::class, 'Invalid resources/read result from server.');
});

it('returns an empty result when resources/read omits contents', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [],
    ]);

    $result = (new Client($transport))->readResource('file://empty');

    expect($result)
        ->toBeInstanceOf(ResourceReadResult::class)
        ->contents->toBe([])
        ->meta->toBeNull()
        ->and($result->content())->toBe('')
        ->and((string) $result)->toBe('');
});

it('filters out non-array content items from a resources/read result', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => [
                'not-an-object',
                ['uri' => 'file://readme', 'text' => 'Hi'],
            ],
        ],
    ]);

    $result = (new Client($transport))->readResource('file://readme');

    expect($result->contents)->toHaveCount(1)
        ->and($result->content())->toBe('Hi');
});

it('coerces a non-array _meta from resources/read to null', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'contents' => [['uri' => 'file://readme', 'text' => 'Hi']],
            '_meta' => 'not-an-object',
        ],
    ]);

    $result = (new Client($transport))->readResource('file://readme');

    expect($result->meta)->toBeNull();
});

it('returns an unbound resource whose read throws until it is bound', function (): void {
    $resource = Resource::from(null, ['uri' => 'file://readme', 'name' => 'readme']);

    expect(fn (): ResourceReadResult => $resource->read())
        ->toThrow(ClientException::class, 'Resource [file://readme] is not bound to a client.');
});

it('reads a bound resource returned from resources()', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['resources' => [['uri' => 'file://readme', 'name' => 'readme']]],
    ]);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['contents' => [['uri' => 'file://readme', 'mimeType' => 'text/plain', 'text' => 'Hello, world!']]],
    ]);

    $resource = (new Client($transport))->resources()['file://readme'];

    expect($resource->read()->content())->toBe('Hello, world!')
        ->and(json_decode($transport->sent[3], true))
        ->toHaveKey('method', 'resources/read')
        ->toHaveKey('params.uri', 'file://readme');
});
