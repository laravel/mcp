<?php

use Laravel\Mcp\Server\Pagination\CursorPaginator;

it('paginates collections correctly', function () {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
        ['name' => 'Item 4'],
        ['name' => 'Item 5'],
    ]);

    $paginator = new CursorPaginator($items, 2);
    $result = $paginator->paginate();

    expect($result['items'])->toHaveCount(2);
    expect($result['nextCursor'])->not->toBeNull();
    expect($result['items'])->toEqual([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
    ]);
});

it('handles cursor based pagination', function () {
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

    expect($secondPage['items'])->toHaveCount(2);
    expect($secondPage['items'])->toEqual([
        ['name' => 'Item 3'],
        ['name' => 'Item 4'],
    ]);
});

it('handles last page correctly', function () {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
        ['name' => 'Item 3'],
    ]);

    $paginator = new CursorPaginator($items, 5);
    $result = $paginator->paginate();

    expect($result['items'])->toHaveCount(3);
    $this->assertArrayNotHasKey('nextCursor', $result);
});

it('handles invalid cursor gracefully', function () {
    $items = collect([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
    ]);

    $paginator = new CursorPaginator($items, 2, 'invalid-cursor');
    $result = $paginator->paginate();

    expect($result['items'])->toHaveCount(2);
    expect($result['items'])->toEqual([
        ['name' => 'Item 1'],
        ['name' => 'Item 2'],
    ]);
});
