<?php

declare(strict_types=1);

it('measures the body, not the root element, when reporting the widget size', function (string $file): void {
    $source = (string) file_get_contents(__DIR__.'/../../../resources/js/'.$file);

    expect((string) preg_replace('/\s+/', '', $source))
        ->toMatch('/height:\w+\.scrollHeight\+parseFloat\(\w+\.marginTop\)\+parseFloat\(\w+\.marginBottom\)/')
        ->toMatch('/width:\w+\.scrollWidth\+parseFloat\(\w+\.marginLeft\)\+parseFloat\(\w+\.marginRight\)/');
})->with([
    'source' => 'mcp-sdk.js',
    'minified' => 'mcp-sdk.min.js',
]);
