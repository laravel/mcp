<?php

use Laravel\Mcp\Server\Resources\Uri;

it('returns a valid path from a uri', function (): void {
    $uri = 'file://resource/path';
    $path = Uri::path($uri);

    expect($path)->toBe('resource/path');
});

it('returns a valid path regex from a uri', function (): void {
    $uri = 'file://resource/path';
    $result = Uri::pathRegex($uri);

    expect($result)->toBe([
        'staticPrefix' => 'resource/path',
        'regex' => '{^resource/path$}sDu',
        'tokens' => [
            [
                'text',
                'resource/path',
            ],
        ],
        'variables' => [],
    ]);
});

it('returns a valid path even if the regex is at the beginning', function (): void {
    $uri = '{variable}/resource/path';
    $path = Uri::pathRegex($uri);

    expect($path['variables'])->toBe(['variable']);
});
