<?php

use Illuminate\Http\Response;
use Laravel\Mcp\Server\Transport\FakeTransporter;

it('implements transport interface', function (): void {
    $transporter = new FakeTransporter;

    expect($transporter)->toBeInstanceOf(\Laravel\Mcp\Server\Contracts\Transport::class);
});

it('can receive handler', function (): void {
    $transporter = new FakeTransporter;
    $called = false;

    $transporter->onReceive(function () use (&$called): void {
        $called = true;
    });

    $response = $transporter->run();

    expect($called)->toBeTrue()
        ->and($response)->toBeInstanceOf(Response::class);
});

it('returns json response when run', function (): void {
    $transporter = new FakeTransporter;

    $response = $transporter->run();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/json')
        ->and($response->getContent())->toBe('');
});

it('calls handler with empty string', function (): void {
    $transporter = new FakeTransporter;
    $receivedMessage = null;

    $transporter->onReceive(function (string $message) use (&$receivedMessage): void {
        $receivedMessage = $message;
    });

    $transporter->run();

    expect($receivedMessage)->toBe('');
});

it('returns unique session ids', function (): void {
    $transporter = new FakeTransporter;

    $sessionId1 = $transporter->sessionId();
    $sessionId2 = $transporter->sessionId();

    expect($sessionId1)->toBeString();
    expect($sessionId2)->toBeString();
    expect($sessionId1)->not->toEqual($sessionId2);
});

it('does nothing when sending', function (): void {
    $transporter = new FakeTransporter;

    $transporter->send('test message');
    $transporter->send('test message', 'session-id');

    expect(true)->toBeTrue();
});

it('does nothing when streaming', function (): void {
    $transporter = new FakeTransporter;

    $transporter->stream(fn (): string => 'test');

    expect(true)->toBeTrue();
});
