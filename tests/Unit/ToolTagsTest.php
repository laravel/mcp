<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Tool;
use Tests\Fixtures\TaggedTool;

it('returns an empty tags array by default', function (): void {
    $tool = new class extends Tool
    {
        public function handle(): string
        {
            return '';
        }
    };

    expect($tool->tags())->toBe([]);
});

it('returns the tags declared on the tool', function (): void {
    $tool = new TaggedTool;

    expect($tool->tags())->toBe(['weather']);
});

it('can declare multiple tags', function (): void {
    $tool = new class extends Tool
    {
        protected array $tags = ['forecast', 'weather'];

        public function handle(): string
        {
            return '';
        }
    };

    expect($tool->tags())->toBe(['forecast', 'weather']);
});
