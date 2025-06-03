<?php

namespace Laravel\Mcp\Tests\Unit\Pagination;

use Laravel\Mcp\Pagination\CursorPaginator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CursorPaginatorTest extends TestCase
{
    #[Test]
    public function it_paginates_collections_correctly()
    {
        $items = collect([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
            ['id' => 4, 'name' => 'Item 4'],
            ['id' => 5, 'name' => 'Item 5'],
        ]);

        $paginator = new CursorPaginator($items, 2);
        $result = $paginator->paginate();

        $this->assertCount(2, $result['items']);
        $this->assertNotNull($result['nextCursor']);
        $this->assertEquals([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ], $result['items']->toArray());
    }

    #[Test]
    public function it_handles_cursor_based_pagination()
    {
        $items = collect([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
            ['id' => 4, 'name' => 'Item 4'],
            ['id' => 5, 'name' => 'Item 5'],
        ]);

        $paginator = new CursorPaginator($items, 2);
        $firstPage = $paginator->paginate();

        $paginator = new CursorPaginator($items, 2, $firstPage['nextCursor']);
        $secondPage = $paginator->paginate();

        $this->assertCount(2, $secondPage['items']);
        $this->assertEquals([
            ['id' => 3, 'name' => 'Item 3'],
            ['id' => 4, 'name' => 'Item 4'],
        ], $secondPage['items']->toArray());
    }

    #[Test]
    public function it_handles_last_page_correctly()
    {
        $items = collect([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
            ['id' => 3, 'name' => 'Item 3'],
        ]);

        $paginator = new CursorPaginator($items, 5);
        $result = $paginator->paginate();

        $this->assertCount(3, $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    #[Test]
    public function it_handles_invalid_cursor_gracefully()
    {
        $items = collect([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ]);

        $paginator = new CursorPaginator($items, 2, 'invalid-cursor');
        $result = $paginator->paginate();

        $this->assertCount(2, $result['items']);
        $this->assertEquals([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ], $result['items']->toArray());
    }
}
