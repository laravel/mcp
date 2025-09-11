<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Pagination;

use Illuminate\Support\Collection;
use Throwable;

class CursorPaginator
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(protected Collection $items, protected int $perPage = 10, protected ?string $cursor = null)
    {
        $this->items = $items->values();
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
        if (! is_string($this->cursor)) {
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
        } catch (Throwable) {
            //
        }

        return 0;
    }

    protected function createCursor(int $offset): string
    {
        $cursorData = ['offset' => $offset];

        return base64_encode((string) json_encode($cursorData));
    }
}
