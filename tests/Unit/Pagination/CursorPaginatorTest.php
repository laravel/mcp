<?php

use Laravel\Mcp\Server\Pagination\CursorPaginator;

it('paginates collections correctly', function (): void {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
        ['name' => 'Item 4'],
        ['name' => 'Item 5'],
    ]);

    $paginator = new CursorPaginator($items, 2);
    $result = $paginator->paginate();

    expect($result['items'])->toHaveCount(2)
        ->and($result['nextCursor'])->not->toBeNull()
        ->and($result['items'])->toEqual([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ]);
});

it('handles cursor based pagination', function (): void {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
        ['name' => 'Item 4'],
        ['name' => 'Item 5'],
    ]);

    $paginator = new CursorPaginator($items, 2);
    $firstPage = $paginator->paginate();

    $paginator = new CursorPaginator($items, 2, $firstPage['nextCursor']);
    $secondPage = $paginator->paginate();

    expect($secondPage['items'])->toHaveCount(2)
        ->and($secondPage['items'])->toEqual([
            ['name' => 'Item 3'],
            ['name' => 'Item 4'],
        ]);
});

it('handles last page correctly', function (): void {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
    ]);

    $paginator = new CursorPaginator($items, 5);
    $result = $paginator->paginate();

    expect($result['items'])->toHaveCount(3)
        ->and($result)->not->toHaveKey('nextCursor');
});

it('handles invalid cursor gracefully', function (): void {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
    ]);

    $paginator = new CursorPaginator($items, 2, 'invalid-cursor');
    $result = $paginator->paginate();

    expect($result['items'])->toHaveCount(2)
        ->and($result['items'])->toEqual([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ]);
});

it('falls back to the first page for cursors that do not decode to an offset', function (string $cursor): void {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
    ]);

    $result = (new CursorPaginator($items, 2, $cursor))->paginate();

    expect($result['items'])->toEqual([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
    ]);
})->with([
    'non-array JSON' => [base64_encode((string) json_encode(123))],
    'valid base64 but invalid JSON' => [base64_encode('not-json{')],
    'object without an offset key' => [base64_encode((string) json_encode(['foo' => 'bar']))],
]);

it('falls back to the first page for a negative offset', function (): void {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
    ]);

    $cursor = base64_encode((string) json_encode(['offset' => -1]));

    $result = (new CursorPaginator($items, 2, $cursor))->paginate();

    expect($result['items'])->toEqual([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
    ]);
});
