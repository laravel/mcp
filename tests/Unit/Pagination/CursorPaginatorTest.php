<?php

namespace Laravel\Mcp\Tests\Unit\Pagination;

use Laravel\Mcp\Server\Pagination\CursorPaginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CursorPaginatorTest extends TestCase
{
    #[Test]
    public function it_paginates_collections_correctly()
    {
        $items = collect([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
            ['name' => 'Item 3'],
            ['name' => 'Item 4'],
            ['name' => 'Item 5'],
        ]);

        $paginator = new CursorPaginator($items, 2);
        $result = $paginator->paginate();

        $this->assertCount(2, $result['items']);
        $this->assertNotNull($result['nextCursor']);
        $this->assertEquals([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ], $result['items']);
    }

    #[Test]
    public function it_handles_cursor_based_pagination()
    {
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

        $this->assertCount(2, $secondPage['items']);
        $this->assertEquals([
            ['name' => 'Item 3'],
            ['name' => 'Item 4'],
        ], $secondPage['items']);
    }

    #[Test]
    public function it_handles_last_page_correctly()
    {
        $items = collect([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
            ['name' => 'Item 3'],
        ]);

        $paginator = new CursorPaginator($items, 5);
        $result = $paginator->paginate();

        $this->assertCount(3, $result['items']);
        $this->assertArrayNotHasKey('nextCursor', $result);
    }

    #[Test]
    public function it_handles_invalid_cursor_gracefully()
    {
        $items = collect([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ]);

        $paginator = new CursorPaginator($items, 2, 'invalid-cursor');
        $result = $paginator->paginate();

        $this->assertCount(2, $result['items']);
        $this->assertEquals([
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ], $result['items']);
    }
}
