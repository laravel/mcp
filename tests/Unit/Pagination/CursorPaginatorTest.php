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
