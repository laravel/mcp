<?php

use Laravel\Mcp\Server\Support\UriTemplateMatcher;

it('matches exact URI without variables', function (): void {
    expect(UriTemplateMatcher::matches('file://resources/users', 'file://resources/users'))->toBeTrue()
        ->and(UriTemplateMatcher::matches('file://resources/users', 'file://resources/posts'))->toBeFalse();
});

it('matches URI with single variable', function (): void {
    $template = 'file://resources/user/{id}';

    expect(UriTemplateMatcher::matches($template, 'file://resources/user/123'))->toBeTrue()
        ->and(UriTemplateMatcher::matches($template, 'file://resources/user/abc'))->toBeTrue()
        ->and(UriTemplateMatcher::matches($template, 'file://resources/post/123'))->toBeFalse()
        ->and(UriTemplateMatcher::matches($template, 'file://resources/user/123/extra'))->toBeFalse();
});

it('matches URI with multiple variables', function (): void {
    $template = 'file://projects/{projectId}/files/{path}';

    expect(UriTemplateMatcher::matches($template, 'file://projects/123/files/readme.md'))->toBeTrue()
        ->and(UriTemplateMatcher::matches($template, 'file://projects/abc/files/src'))->toBeTrue()
        ->and(UriTemplateMatcher::matches($template, 'file://projects/123/documents/readme.md'))->toBeFalse();
});

it('does not match URI with forward slashes in variable', function (): void {
    $template = 'file://resources/{id}';

    // Variables should not match across path segments (forward slashes)
    expect(UriTemplateMatcher::matches($template, 'file://resources/123/456'))->toBeFalse();
});

it('extracts single variable from URI', function (): void {
    $template = 'file://resources/user/{id}';
    $uri = 'file://resources/user/123';

    $variables = UriTemplateMatcher::extract($template, $uri);

    expect($variables)->toBe(['id' => '123']);
});

it('extracts multiple variables from URI', function (): void {
    $template = 'file://projects/{projectId}/files/{filename}';
    $uri = 'file://projects/my-project/files/readme.md';

    $variables = UriTemplateMatcher::extract($template, $uri);

    expect($variables)->toBe([
        'projectId' => 'my-project',
        'filename' => 'readme.md',
    ]);
});

it('extracts variables with underscores and numbers', function (): void {
    $template = 'file://api/{user_id}/posts/{post_id2}';
    $uri = 'file://api/user123/posts/post456';

    $variables = UriTemplateMatcher::extract($template, $uri);

    expect($variables)->toBe([
        'user_id' => 'user123',
        'post_id2' => 'post456',
    ]);
});

it('returns empty array when extraction fails', function (): void {
    $template = 'file://resources/user/{id}';
    $uri = 'file://resources/post/123';

    $variables = UriTemplateMatcher::extract($template, $uri);

    expect($variables)->toBe([]);
});

it('handles special characters in static parts', function (): void {
    $template = 'file://resources/user-profile/{id}';
    $uri = 'file://resources/user-profile/123';

    expect(UriTemplateMatcher::matches($template, $uri))->toBeTrue();

    $variables = UriTemplateMatcher::extract($template, $uri);
    expect($variables)->toBe(['id' => '123']);
});

it('handles URL with query parameters', function (): void {
    $template = 'https://api.example.com/users/{id}';

    // Variables can include query params since {id} matches [^/]+ (anything except /)
    expect(UriTemplateMatcher::matches($template, 'https://api.example.com/users/123?foo=bar'))->toBeTrue();

    // Exact match without query params
    expect(UriTemplateMatcher::matches($template, 'https://api.example.com/users/123'))->toBeTrue();

    // Extract includes query params in variable
    $vars = UriTemplateMatcher::extract($template, 'https://api.example.com/users/123?foo=bar');
    expect($vars)->toBe(['id' => '123?foo=bar']);
});

it('handles complex nested paths', function (): void {
    $template = 'file://projects/{org}/{repo}/issues/{number}';
    $uri = 'file://projects/laravel/framework/issues/42';

    expect(UriTemplateMatcher::matches($template, $uri))->toBeTrue();

    $variables = UriTemplateMatcher::extract($template, $uri);
    expect($variables)->toBe([
        'org' => 'laravel',
        'repo' => 'framework',
        'number' => '42',
    ]);
});

it('distinguishes between similar templates', function (): void {
    $template1 = 'file://resources/user/{id}';
    $template2 = 'file://resources/post/{id}';

    $uri = 'file://resources/user/123';

    expect(UriTemplateMatcher::matches($template1, $uri))->toBeTrue()
        ->and(UriTemplateMatcher::matches($template2, $uri))->toBeFalse();
});
