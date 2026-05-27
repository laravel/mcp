<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\ClientRegistration;

it('round-trips through fromArray and toArray', function (): void {
    $registration = new ClientRegistration('cid', 'secret', 1_700_000_000, 0);

    expect(ClientRegistration::fromArray($registration->toArray()))->toEqual($registration);
});

it('normalizes an empty client secret to null on fromArray', function (): void {
    $registration = ClientRegistration::fromArray([
        'client_id' => 'cid',
        'client_secret' => '',
    ]);

    expect($registration->clientSecret)->toBeNull();
});

it('reports isSecretExpired() honors the explicit expiry timestamp', function (): void {
    $expired = new ClientRegistration('cid', 'secret', null, time() - 5);
    $nonExpiring = new ClientRegistration('cid', 'secret', null, 0);
    $future = new ClientRegistration('cid', 'secret', null, time() + 600);
    $noSecret = new ClientRegistration('cid');

    expect($expired->isSecretExpired())->toBeTrue()
        ->and($nonExpiring->isSecretExpired())->toBeFalse()
        ->and($future->isSecretExpired())->toBeFalse()
        ->and($noSecret->isSecretExpired())->toBeFalse();
});
