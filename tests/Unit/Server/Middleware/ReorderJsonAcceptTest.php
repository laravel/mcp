<?php

use Illuminate\Http\Request;
use Laravel\Mcp\Server\Middleware\ReorderJsonAccept;

it('leaves single accept header unchanged', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'application/json');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('application/json');
});

it('leaves non-comma separated accept header unchanged', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'text/html');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('text/html');
});

it('reorders multiple accept headers to prioritize json', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'text/html, application/json, text/plain');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('application/json, text/html, text/plain');
});

it('handles json already first in list', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'application/json, text/html, text/plain');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('application/json, text/html, text/plain');
});

it('handles multiple json types correctly', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'text/html, application/json, application/vnd.api+json, text/plain');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    $accept = $request->header('Accept');
    $parts = array_map(trim(...), explode(',', $accept));

    expect($parts)->toMatchArray(['application/json', 'text/html', 'application/vnd.api+json', 'text/plain'])
        ->and(count($parts))->toBe(4);
});

it('handles accept header with quality values', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'text/html;q=0.9, application/json;q=0.8, text/plain;q=0.7');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    $accept = $request->header('Accept');
    $parts = array_map(trim(...), explode(',', $accept));

    expect($parts[0])->toBe('application/json;q=0.8');
});

it('handles whitespace in accept header', function (): void {
    $request = new Request;
    $request->headers->set('Accept', '  text/html  ,  application/json  ,  text/plain  ');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('application/json, text/html, text/plain');
});

it('handles no json in accept header', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'text/html, text/plain, image/png');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('text/html, text/plain, image/png');
});

it('handles empty accept header', function (): void {
    $request = new Request;
    $request->headers->set('Accept', '');

    $middleware = new ReorderJsonAccept;

    $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($request->header('Accept'))->toBe('');
});

it('handles missing accept header', function (): void {
    $request = new Request;

    $middleware = new ReorderJsonAccept;

    $response = $middleware->handle($request, fn ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('test'));

    expect($response->getContent())->toBe('test');
});

it('passes request through middleware correctly', function (): void {
    $request = new Request;
    $request->headers->set('Accept', 'text/html, application/json');

    $middleware = new ReorderJsonAccept;

    $response = $middleware->handle($request, function ($req): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response {
        expect($req->header('Accept'))->toBe('application/json, text/html');

        return response('middleware worked');
    });

    expect($response->getContent())->toBe('middleware worked');
});
