<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Pagination;

use Illuminate\Support\Collection;
use Throwable;

class CursorPaginator
{
    private Collection $items;

    private int $perPage;

    private ?string $cursor;

    /**
     * Create a new cursor paginator.
     */
    public function __construct(Collection $items, int $perPage = 10, ?string $cursor = null)
    {
        $this->items = $items->values();
        $this->perPage = $perPage;
        $this->cursor = $cursor;
    }

    /**
     * Paginate the items using a cursor.
     */
    public function paginate(string $key = 'items'): array
    {
        $startOffset = $this->getStartOffsetFromCursor();

        $paginatedItems = $this->items->slice($startOffset, $this->perPage);

        $hasMorePages = $this->items->count() > ($startOffset + $this->perPage);

        $result = [$key => $paginatedItems->values()->toArray()];

        if ($hasMorePages) {
            $result['nextCursor'] = $this->createCursor($startOffset + $this->perPage);
        }

        return $result;
    }

    /**
     * Get the start offset from the cursor.
     */
    private function getStartOffsetFromCursor(): int
    {
        if (! $this->cursor) {
            return 0;
        }

        try {
            $decodedCursor = base64_decode($this->cursor, true);

            if ($decodedCursor === false) {
                return 0;
            }

            $cursorData = json_decode($decodedCursor, true);

            if (! is_array($cursorData)) {
                return 0;
            }

            return (int) ($cursorData['offset'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Create a cursor from the offset.
     */
    private function createCursor(int $offset): string
    {
        $cursorData = ['offset' => $offset];

        return base64_encode(json_encode($cursorData));
    }
}
