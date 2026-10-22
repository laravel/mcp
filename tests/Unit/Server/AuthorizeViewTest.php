<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function renderAuthorize(Request $request, array $client = []): string
{
    Route::delete('/oauth/authorize', fn (): null => null)->name('passport.authorizations.deny');
    Route::post('/oauth/authorize', fn (): null => null)->name('passport.authorizations.approve');

    return view('mcp::authorize', [
        'client' => (object) ['id' => 'client-1', 'name' => 'Example', ...$client],
        'user' => (object) ['email' => 'user@example.com'],
        'scopes' => [],
        'authToken' => 'token',
        'request' => $request,
    ])->render();
}

it('shows the requested redirect uri and its host', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize', 'GET', ['redirect_uri' => 'https://app.example.com/callback']));

    expect($html)->toContain('You will be redirected to')
        ->and($html)->toContain('app.example.com')
        ->and($html)->toContain('https://app.example.com/callback');
});

it('falls back to the registered redirect uri', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize'), ['redirect_uris' => ['https://registered.example.com/cb']]);

    expect($html)->toContain('registered.example.com');
});

it('escapes the redirect uri', function (): void {
    $this->withoutVite();

    $html = renderAuthorize(Request::create('/oauth/authorize', 'GET', ['redirect_uri' => 'https://x.test/<script>alert(1)</script>']));

    expect($html)->not->toContain('<script>alert(1)</script>');
});
