<?php

use Laravel\Mcp\Server\Transport\FakeTransporter;
use LogicException;

it('throws when running since it is not implemented', function (): void {
    $transporter = new FakeTransporter;

    $transporter->run();
})->throws(LogicException::class, 'Not implemented.');

it('generates a non-empty, unique session id', function (): void {
    $transporter = new FakeTransporter;

    $id1 = $transporter->sessionId();
    $id2 = $transporter->sessionId();

    expect($id1)->toBeString()->not->toBe('')->and($id2)->toBeString()->not->toBe('');
    expect($id1)->not->toBe($id2);
});

it('accepts onReceive handler without side effects', function (): void {
    $transporter = new FakeTransporter;

    $called = false;
    $transporter->onReceive(function () use (&$called): void {
        $called = true;
    });

    expect($called)->toBeFalse(); // FakeTransporter does not invoke the handler
});

it('send is a no-op and does not throw', function (): void {
    $transporter = new FakeTransporter;

    $transporter->send('{"ping":true}');
    $transporter->send('{"ping":true}', 'custom-session');

    expect(true)->toBeTrue();
});

it('stream accepts a closure and does nothing', function (): void {
    $transporter = new FakeTransporter;

    $didRun = false;
    $transporter->stream(function () use (&$didRun): void {
        $didRun = true; // FakeTransporter should not execute this
    });

    expect($didRun)->toBeFalse();
});
