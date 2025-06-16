<?php

namespace Laravel\Mcp\Pagination;

use Illuminate\Support\Collection;
use Throwable;

class CursorPaginator
{
    private Collection $items;
    private int $perPage;
    private ?string $cursor;
    private string $idField;

    /**
     * Create a new cursor paginator.
     */
    public function __construct(Collection $items, int $perPage = 10, ?string $cursor = null, string $idField = 'id')
    {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->cursor = $cursor;
        $this->idField = $idField;
    }

    /**
     * Paginate the items using a cursor.
     */
    public function paginate(): array
    {
        $startId = $this->getStartIdFromCursor();

        $filteredItems = $this->items->filter(fn($item) => $item[$this->idField] >= $startId);

        $paginatedItems = $filteredItems->take($this->perPage);

        $hasMorePages = $filteredItems->count() > $this->perPage;

        $nextCursor = null;

        if ($hasMorePages) {
            $lastItem = $paginatedItems->last();
            $nextCursor = $this->createCursor($lastItem[$this->idField]);
        }

        return [
            'items' => $paginatedItems->values(),
            'nextCursor' => $nextCursor,
        ];
    }

    /**
     * Get the start ID from the cursor.
     */
    private function getStartIdFromCursor(): int
    {
        if (! $this->cursor) {
            return 1;
        }

        try {
            $decodedCursor = base64_decode($this->cursor, true);

            if ($decodedCursor === false) {
                return 1;
            }

            $cursorData = json_decode($decodedCursor, true);

            if (! is_array($cursorData)) {
                return 1;
            }

            return ($cursorData['last_id'] ?? 0) + 1;
        } catch (Throwable $e) {
            return 1;
        }
    }

    /**
     * Create a cursor from the last ID.
     */
    private function createCursor(int $lastId): string
    {
        $cursorData = ['last_id' => $lastId];

        return base64_encode(json_encode($cursorData));
    }
}
