<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Elicitation\ElicitationResult;

it('reports accepted action', function (): void {
    $result = new ElicitationResult('accept', ['name' => 'Taylor']);

    expect($result->accepted())->toBeTrue();
    expect($result->declined())->toBeFalse();
    expect($result->cancelled())->toBeFalse();
    expect($result->action())->toBe('accept');
});

it('reports declined action', function (): void {
    $result = new ElicitationResult('decline');

    expect($result->declined())->toBeTrue();
    expect($result->accepted())->toBeFalse();
    expect($result->cancelled())->toBeFalse();
});

it('reports cancelled action', function (): void {
    $result = new ElicitationResult('cancel');

    expect($result->cancelled())->toBeTrue();
    expect($result->accepted())->toBeFalse();
    expect($result->declined())->toBeFalse();
});

it('can get content values', function (): void {
    $result = new ElicitationResult('accept', ['name' => 'Taylor', 'age' => 30]);

    expect($result->get('name'))->toBe('Taylor');
    expect($result->get('age'))->toBe(30);
    expect($result->get('missing'))->toBeNull();
    expect($result->get('missing', 'default'))->toBe('default');
});

it('can get all content', function (): void {
    $result = new ElicitationResult('accept', ['name' => 'Taylor']);

    expect($result->all())->toBe(['name' => 'Taylor']);
});

it('returns empty array when content is null', function (): void {
    $result = new ElicitationResult('decline');

    expect($result->all())->toBe([]);
});

it('can set and get elicitation id', function (): void {
    $result = new ElicitationResult('accept');

    expect($result->elicitationId())->toBeNull();

    $result->setElicitationId('test-id-123');

    expect($result->elicitationId())->toBe('test-id-123');
});
