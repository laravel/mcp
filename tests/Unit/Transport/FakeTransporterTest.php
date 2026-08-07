<?php

use Laravel\Mcp\Server\Transport\FakeTransporter;

it('throws when running since it is not implemented', function (): void {
    $transporter = new FakeTransporter;

    $transporter->run();
})->throws(LogicException::class, 'Not implemented.');

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
