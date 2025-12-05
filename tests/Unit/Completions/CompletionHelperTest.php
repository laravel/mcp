<?php

use Laravel\Mcp\Server\Completions\CompletionHelper;

describe('filterByPrefix', function (): void {
    it('filters items by prefix case-insensitively', function (): void {
        $items = ['php', 'python', 'javascript', 'java'];

        $result = CompletionHelper::filterByPrefix($items, 'py');

        expect($result)->toBe(['python']);
    });

    it('returns all items when prefix is empty', function (): void {
        $items = ['php', 'python', 'javascript'];

        $result = CompletionHelper::filterByPrefix($items, '');

        expect($result)->toBe($items);
    });

    it('handles case-insensitive matching', function (): void {
        $items = ['PHP', 'Python', 'JavaScript'];

        $result = CompletionHelper::filterByPrefix($items, 'py');

        expect($result)->toBe(['Python']);
    });

    it('returns empty array when no matches', function (): void {
        $items = ['php', 'python'];

        $result = CompletionHelper::filterByPrefix($items, 'rust');

        expect($result)->toBe([]);
    });
});
