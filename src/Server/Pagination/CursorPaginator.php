<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Pagination;

use Illuminate\Support\Collection;
use Throwable;

class CursorPaginator
{
    /**
     * @var Collection<int, mixed>
     */
    protected Collection $items;

    protected int $perPage;

    protected ?string $cursor;

    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(Collection $items, int $perPage = 10, ?string $cursor = null)
    {
        $this->items = $items->values();
        $this->perPage = $perPage;
        $this->cursor = $cursor;
    }

    /**
     * @return array<string, mixed>
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

    protected function getStartOffsetFromCursor(): int
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

    protected function createCursor(int $offset): string
    {
        $cursorData = ['offset' => $offset];

        return base64_encode(json_encode($cursorData));
    }
}
