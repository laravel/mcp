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

it('creates a structured content response with Response::structured', function (): void {
    $factory = Response::structured(['result' => 'The result of the tool.']);

    expect($factory)->toBeInstanceOf(ResponseFactory::class)
        ->and($factory->getStructuredContent())->toEqual(['result' => 'The result of the tool.'])
        ->and($factory->responses())->toHaveCount(1);

    $textResponse = $factory->responses()->first();
    expect($textResponse->content()->toArray()['text'])
        ->toContain('"result":"The result of the tool."');
});

it('creates a structured content response with meta using Response::structured', function (): void {
    $factory = Response::structured([
        'entry' => 'The result of the tool.',
    ])->withMeta(['x' => 'y']);

    expect($factory)->toBeInstanceOf(ResponseFactory::class)
        ->and($factory->getStructuredContent())->toEqual(['entry' => 'The result of the tool.'])
        ->and($factory->getMeta())->toEqual(['x' => 'y'])
        ->and($factory->responses())->toHaveCount(1);
});

it('adds structured content to existing ResponseFactory with withStructuredContent', function (): void {
    $factory = Response::make([
        Response::text('result is this'),
    ])->withStructuredContent(['result' => 'result is this']);

    expect($factory)->toBeInstanceOf(ResponseFactory::class)
        ->and($factory->getStructuredContent())->toEqual(['result' => 'result is this'])
        ->and($factory->responses())->toHaveCount(1);

    $textResponse = $factory->responses()->first();
    expect($textResponse->content()->toArray()['text'])->toBe('result is this');
});

it('adds structured content with meta to ResponseFactory', function (): void {
    $factory = Response::make([
        Response::text('result is this')->withMeta(['x' => 'y']),
    ])->withStructuredContent(['result' => 'result is this']);

    expect($factory)->toBeInstanceOf(ResponseFactory::class)
        ->and($factory->getStructuredContent())->toEqual(['result' => 'result is this'])
        ->and($factory->responses())->toHaveCount(1);
});

it('merges multiple withStructuredContent calls', function (): void {
    $factory = (new ResponseFactory(Response::text('Hello')))
        ->withStructuredContent(['key1' => 'value1'])
        ->withStructuredContent(['key2' => 'value1'])
        ->withStructuredContent(['key2' => 'value2']);

    expect($factory->getStructuredContent())->toEqual(['key1' => 'value1', 'key2' => 'value2']);
});

it('throws exception when Response::structured result is wrapped in ResponseFactory::make', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::structured(['result' => 'The result of the tool.']),
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});
