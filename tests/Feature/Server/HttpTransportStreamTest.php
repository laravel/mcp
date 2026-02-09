<?php

use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Transport\HttpTransport;

it('streams iterable responses returned from the stream callback', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(fn (): iterable => [
        '{"jsonrpc":"2.0","id":1,"result":[]}',
        '{"jsonrpc":"2.0","id":2,"result":[]}',
    ]);

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":1,"result":[]}');
    expect($content)->toContain('data: {"jsonrpc":"2.0","id":2,"result":[]}');
});

it('streams generator responses returned from the stream callback', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(function (): Generator {
        yield '{"jsonrpc":"2.0","id":3,"result":[]}';
        yield '{"jsonrpc":"2.0","id":4,"result":[]}';
    });

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":3,"result":[]}');
    expect($content)->toContain('data: {"jsonrpc":"2.0","id":4,"result":[]}');
});

it('streams generator responses when Octane is flagged', function (): void {
    $_SERVER['LARAVEL_OCTANE'] = '1';

    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(function (): Generator {
        yield '{"jsonrpc":"2.0","id":5,"result":[]}';
    });

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toContain('data: {"jsonrpc":"2.0","id":5,"result":[]}');

    unset($_SERVER['LARAVEL_OCTANE']);
});

it('does not double emit when stream callback echoes directly', function (): void {
    $transport = new HttpTransport(request(), 'test-session');

    $transport->stream(function (): void {
        echo 'data: {"jsonrpc":"2.0","id":99,"result":[]}';
        echo "\n\n";
    });

    $response = $transport->run();

    $testResponse = TestResponse::fromBaseResponse($response);
    $content = $testResponse->streamedContent();

    expect($content)->toBe("data: {\"jsonrpc\":\"2.0\",\"id\":99,\"result\":[]}\n\n");
});
