<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

it('creates a factory with a single response', function (): void {
    $response = Response::text('Hello');
    $factory = ResponseFactory::make($response);

    expect($factory)->toBeInstanceOf(ResponseFactory::class);
    expect($factory->responses())
        ->toHaveCount(1)
        ->first()->toBe($response);
});

it('creates a factory with multiple responses', function (): void {
    $response1 = Response::text('First');
    $response2 = Response::text('Second');
    $factory = ResponseFactory::make([$response1, $response2]);

    expect($factory)->toBeInstanceOf(ResponseFactory::class);
    expect($factory->responses())
        ->toHaveCount(2)
        ->first()->toBe($response1);
    expect($factory->responses()->last())->toBe($response2);
});

it('supports fluent withMeta for result-level metadata', function (): void {
    $factory = ResponseFactory::make(Response::text('Hello'))
        ->withMeta(['key' => 'value']);

    expect($factory->getMeta())->toEqual(['key' => 'value']);
});

it('supports withMeta with key-value signature', function (): void {
    $factory = ResponseFactory::make(Response::text('Hello'))
        ->withMeta('key1', 'value1')
        ->withMeta('key2', 'value2');

    expect($factory->getMeta())->toEqual(['key1' => 'value1', 'key2' => 'value2']);
});

it('merges multiple withMeta calls', function (): void {
    $factory = ResponseFactory::make(Response::text('Hello'))
        ->withMeta(['key1' => 'value1'])
        ->withMeta(['key2' => 'value1'])
        ->withMeta(['key2' => 'value2']);

    expect($factory->getMeta())->toEqual(['key1' => 'value1', 'key2' => 'value2']);
});

it('returns null for getMeta when no meta is set', function (): void {
    $factory = ResponseFactory::make(Response::text('Hello'));

    expect($factory->getMeta())->toBeNull();
});

it('supports Conditionable trait', function (): void {
    $factory = ResponseFactory::make(Response::text('Hello'))
        ->when(true, fn ($f): ResponseFactory => $f->withMeta(['conditional' => 'yes']));

    expect($factory->getMeta())->toEqual(['conditional' => 'yes']);
});

it('supports unless from Conditionable trait', function (): void {
    $factory = ResponseFactory::make(Response::text('Hello'))
        ->unless(false, fn ($f): ResponseFactory => $f->withMeta(['unless' => 'applied']));

    expect($factory->getMeta())->toEqual(['unless' => 'applied']);
});

it('separates content-level meta from result-level meta', function (): void {
    $response = Response::text('Hello')->withMeta(['content_meta' => 'content_value']);
    $factory = ResponseFactory::make($response)
        ->withMeta(['result_meta' => 'result_value']);

    expect($factory->getMeta())->toEqual(['result_meta' => 'result_value']);
    expect($factory->responses()->first())->toBe($response);
});

it('throws exception when array contains non-Response object', function (): void {
    expect(fn (): ResponseFactory => ResponseFactory::make([
        Response::text('Valid'),
        'Invalid string',
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when array contains nested ResponseFactory', function (): void {
    $nestedFactory = ResponseFactory::make(Response::text('Nested'));

    expect(fn (): ResponseFactory => ResponseFactory::make([
        Response::text('First'),
        $nestedFactory,
        Response::text('Third'),
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when an array contains null', function (): void {
    expect(fn (): ResponseFactory => ResponseFactory::make([
        Response::text('Valid'),
        null,
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('accepts a single Response without validation error', function (): void {
    $response = Response::text('Single response');
    $factory = ResponseFactory::make($response);

    expect($factory->responses())
        ->toHaveCount(1)
        ->first()->toBe($response);
});

it('accepts array of valid Response objects', function (): void {
    $responses = [
        Response::text('First'),
        Response::text('Second'),
        Response::blob('binary'),
    ];

    $factory = ResponseFactory::make($responses);

    expect($factory->responses())
        ->toHaveCount(3)
        ->all()->toBe($responses);
});
