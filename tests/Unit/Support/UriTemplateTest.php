<?php

declare(strict_types=1);

use Laravel\Mcp\Support\UriTemplate;

it('requires a valid URI scheme', function (): void {
    expect(fn (): UriTemplate => new UriTemplate('/invalid/no-scheme/{id}'))
        ->toThrow(InvalidArgumentException::class, 'Invalid URI template: must be a valid URI template with at least one placeholder.');
});

it('requires at least one placeholder', function (): void {
    expect(fn (): UriTemplate => new UriTemplate('file://path/without/placeholder'))
        ->toThrow(InvalidArgumentException::class, 'Invalid URI template: must be a valid URI template with at least one placeholder.');
});

it('accepts valid URI templates', function (): void {
    expect(new UriTemplate('file://users/{id}'))->toBeInstanceOf(UriTemplate::class)
        ->and(new UriTemplate('https://api.example.com/{endpoint}'))->toBeInstanceOf(UriTemplate::class)
        ->and(new UriTemplate('https://example.com/path/{var}'))->toBeInstanceOf(UriTemplate::class);
});

it('extracts variables from simple strings', function (): void {
    $template = new UriTemplate('https://example.com/users/{username}');

    expect($template->match('https://example.com/users/fred'))->toBe(['username' => 'fred']);
});

it('extracts multiple variables', function (): void {
    $template = new UriTemplate('file://users/{username}/posts/{postId}');

    expect($template->match('file://users/fred/posts/123'))->toBe(['username' => 'fred', 'postId' => '123']);
});

it('exposes variable names', function (): void {
    $template = new UriTemplate('file://users/{userId}/files/{fileId}/copies/{fileId}');

    expect($template->variableNames())->toBe(['userId', 'fileId']);
});

it('returns null for non-matching URIs', function (): void {
    $template = new UriTemplate('file://users/{username}');

    expect($template->match('file://posts/123'))->toBeNull();
});

it('matches nested path segments', function (): void {
    $template = new UriTemplate('https://api.example.com/{version}/{resource}/{id}');

    expect($template->match('https://api.example.com/v1/users/123'))->toBe([
        'version' => 'v1',
        'resource' => 'users',
        'id' => '123',
    ]);
});

it('rejects partial matches and incomplete URIs', function (): void {
    $template = new UriTemplate('file://users/{id}');

    expect($template->match('file://users/123/extra'))->toBeNull()
        ->and($template->match('file://users'))->toBeNull();
});

it('does not match variables across slashes', function (): void {
    $template = new UriTemplate('file://files/{path}');

    expect($template->match('file://files/foo/bar'))->toBeNull()
        ->and($template->match('file://files/simple.txt'))->toBe(['path' => 'simple.txt']);
});

it('handles empty segments', function (): void {
    $template = new UriTemplate('file://////{a}////{b}////');

    expect($template->match('file://////1////2////'))->toBe(['a' => '1', 'b' => '2']);
});

it('handles extremely long input strings', function (): void {
    $longString = str_repeat('x', 100000);
    $template = new UriTemplate('https://api.example.com/{param}');

    expect($template->match('https://api.example.com/'.$longString))->toBe(['param' => $longString]);
});

it('throws when the template exceeds the maximum length', function (): void {
    $longTemplate = str_repeat('{x}', 1000001);

    expect(fn (): UriTemplate => new UriTemplate($longTemplate))
        ->toThrow(InvalidArgumentException::class, 'Template exceeds the maximum length');
});

it('throws when URI exceeds maximum length', function (): void {
    $template = new UriTemplate('https://api.example.com/{param}');
    $longUri = 'https://api.example.com/'.str_repeat('x', 1000001);

    expect(fn (): ?array => $template->match($longUri))
        ->toThrow(InvalidArgumentException::class, 'URI exceeds the maximum length');
});

it('throws when the template has too many expressions', function (): void {
    $tooManyExpressions = 'https://example.com/'.str_repeat('{a}', 10001);

    expect(fn (): UriTemplate => new UriTemplate($tooManyExpressions))
        ->toThrow(InvalidArgumentException::class, 'Template contains too many expressions');
});

it('throws for unclosed template expressions', function (): void {
    expect(fn (): UriTemplate => new UriTemplate('https://example.com/{unclosed'))
        ->toThrow(InvalidArgumentException::class);
});

it('handles pathological regex patterns', function (): void {
    $template = new UriTemplate('https://api.example.com/{param}');
    $input = 'https://api.example.com/'.str_repeat('a', 100000);

    expect(fn (): ?array => $template->match($input))->not->toThrow(Exception::class);
});

it('handles invalid UTF-8 sequences', function (): void {
    $template = new UriTemplate('https://api.example.com/{param}');
    $invalidUtf8 = '���';

    expect(fn (): ?array => $template->match('https://api.example.com/'.$invalidUtf8))->not->toThrow(Exception::class);
});

it('handles maximum template expression limit', function (): void {
    $expressions = 'https://example.com/'.str_repeat('{param}', 10000);

    expect(fn (): UriTemplate => new UriTemplate($expressions))->not->toThrow(Exception::class);
});

it('casts to string', function (): void {
    $template = new UriTemplate('file://users/{id}');

    expect((string) $template)->toBe('file://users/{id}');
});

it('expands variables into a uri', function (): void {
    $template = new UriTemplate('file://users/{userId}/files/{fileId}');

    expect($template->expand(['userId' => 42, 'fileId' => 'notes.md']))->toBe('file://users/42/files/notes.md');
});

it('percent encodes expanded values so they stay in one segment', function (): void {
    $template = new UriTemplate('file://files/{filename}');

    $uri = $template->expand(['filename' => 'folder/my notes.md']);

    expect($uri)->toBe('file://files/folder%2Fmy%20notes.md')
        ->and($template->match($uri))->toBe(['filename' => 'folder%2Fmy%20notes.md']);
});

it('requires a string or integer value for every variable when expanding', function (array $variables): void {
    expect(fn (): string => (new UriTemplate('file://users/{userId}'))->expand($variables))
        ->toThrow(InvalidArgumentException::class, 'Missing a value for the [userId] variable of URI template [file://users/{userId}].');
})->with([
    'missing' => [[]],
    'empty' => [['userId' => '']],
    'array' => [['userId' => [1]]],
    'null' => [['userId' => null]],
]);
