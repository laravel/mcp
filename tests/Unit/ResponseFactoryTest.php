<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

it('creates a factory with a single response', function (): void {
    $response = Response::text('Hello');
    $factory = new ResponseFactory($response);

    expect($factory)->toBeInstanceOf(ResponseFactory::class);
    expect($factory->responses())
        ->toHaveCount(1)
        ->first()->toBe($response);
});

it('creates a factory with multiple responses', function (): void {
    $response1 = Response::text('First');
    $response2 = Response::text('Second');
    $factory = new ResponseFactory([$response1, $response2]);

    expect($factory)->toBeInstanceOf(ResponseFactory::class);
    expect($factory->responses())
        ->toHaveCount(2)
        ->first()->toBe($response1);
    expect($factory->responses()->last())->toBe($response2);
});

it('supports fluent withMeta for result-level metadata', function (): void {
    $factory = (new ResponseFactory(Response::text('Hello')))
        ->withMeta(['key' => 'value']);

    expect($factory->getMeta())->toEqual(['key' => 'value']);
});

it('supports withMeta with key-value signature', function (): void {
    $factory = (new ResponseFactory(Response::text('Hello')))
        ->withMeta('key1', 'value1')
        ->withMeta('key2', 'value2');

    expect($factory->getMeta())->toEqual(['key1' => 'value1', 'key2' => 'value2']);
});

it('merges multiple withMeta calls', function (): void {
    $factory = (new ResponseFactory(Response::text('Hello')))
        ->withMeta(['key1' => 'value1'])
        ->withMeta(['key2' => 'value1'])
        ->withMeta(['key2' => 'value2']);

    expect($factory->getMeta())->toEqual(['key1' => 'value1', 'key2' => 'value2']);
});

it('supports Conditionable trait', function (): void {
    $factory = (new ResponseFactory(Response::text('Hello')))
        ->when(true, fn ($f): ResponseFactory => $f->withMeta(['conditional' => 'yes']));

    expect($factory->getMeta())->toEqual(['conditional' => 'yes']);
});

it('supports unless from Conditionable trait', function (): void {
    $factory = (new ResponseFactory(Response::text('Hello')))
        ->unless(false, fn ($f): ResponseFactory => $f->withMeta(['unless' => 'applied']));

    expect($factory->getMeta())->toEqual(['unless' => 'applied']);
});

it('separates content-level meta from result-level meta', function (): void {
    $response = Response::text('Hello')->withMeta(['content_meta' => 'content_value']);
    $factory = (new ResponseFactory($response))
        ->withMeta(['result_meta' => 'result_value']);

    expect($factory->getMeta())->toEqual(['result_meta' => 'result_value'])
        ->and($factory->responses()->first())->toBe($response);
});
