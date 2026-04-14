<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Ui\Enums\AppResourceLibrary;

it('returns correct domains for tailwind', function (): void {
    expect(AppResourceLibrary::Tailwind->domains())
        ->toBe(['https://cdn.tailwindcss.com']);
});

it('returns correct domains for alpine', function (): void {
    expect(AppResourceLibrary::Alpine->domains())
        ->toBe(['https://cdn.jsdelivr.net']);
});

it('returns script tags for tailwind', function (): void {
    $tags = AppResourceLibrary::Tailwind->scriptTags();

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->toContain('cdn.tailwindcss.com')
        ->and($tags[1])->toContain('tailwind.config');
});

it('returns script tags for alpine', function (): void {
    $tags = AppResourceLibrary::Alpine->scriptTags();

    expect($tags)->toHaveCount(2)
        ->and($tags[0])->toContain('x-cloak')
        ->and($tags[1])->toContain('alpinejs');
});
