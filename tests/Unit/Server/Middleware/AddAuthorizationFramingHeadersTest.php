<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Laravel\Mcp\Server\Middleware\AddAuthorizationFramingHeaders;

it('denies framing of the authorization pages', function (string $name): void {
    $request = Request::create('/oauth/authorize');
    $request->setRouteResolver(fn (): Route => (new Route('GET', '/oauth/authorize', []))->name($name));

    $response = (new AddAuthorizationFramingHeaders)->handle($request, fn (): Response => response('consent'));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'none'");
})->with([
    'passport.authorizations.authorize',
    'passport.authorizations.approve',
    'passport.authorizations.deny',
    'passport.device',
    'passport.device.authorizations.authorize',
    'passport.device.authorizations.approve',
    'passport.device.authorizations.deny',
]);

it('replaces a weaker frame options header', function (): void {
    $request = Request::create('/oauth/authorize');
    $request->setRouteResolver(fn (): Route => (new Route('GET', '/oauth/authorize', []))->name('passport.authorizations.authorize'));

    $response = (new AddAuthorizationFramingHeaders)->handle($request, fn (): Response => response('consent', 200, [
        'X-Frame-Options' => 'SAMEORIGIN',
    ]));

    expect($response->headers->all('x-frame-options'))->toBe(['DENY']);
});

it('keeps the content security policy the application already set', function (): void {
    $request = Request::create('/oauth/authorize');
    $request->setRouteResolver(fn (): Route => (new Route('GET', '/oauth/authorize', []))->name('passport.authorizations.authorize'));

    $response = (new AddAuthorizationFramingHeaders)->handle($request, fn (): Response => response('consent', 200, [
        'Content-Security-Policy' => "default-src 'self'",
    ]));

    expect($response->headers->all('content-security-policy'))->toBe(["default-src 'self'", "frame-ancestors 'none'"]);
});

it('leaves other responses untouched', function (): void {
    $request = Request::create('/mcp');
    $request->setRouteResolver(fn (): Route => (new Route('POST', '/mcp', []))->name('mcp'));

    $response = (new AddAuthorizationFramingHeaders)->handle($request, fn (): Response => response('ok'));

    expect($response->headers->has('X-Frame-Options'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse();
});
