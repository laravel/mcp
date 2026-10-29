<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\OAuth\AuthServerDiscovery;
use Laravel\Mcp\Support\AddressGuard;

afterEach(function (): void {
    AddressGuard::resolveHostsUsing(null);
});

it('accepts hosts that only resolve to public addresses', function (): void {
    AddressGuard::resolveHostsUsing(fn (string $host): array => ['93.184.216.34', '2606:4700::1111']);

    expect((new AddressGuard)->isPublicHost('client.example.com'))->toBeTrue();
});

it('rejects hosts that resolve to any special use address', function (string $address): void {
    AddressGuard::resolveHostsUsing(fn (string $host): array => ['93.184.216.34', $address]);

    expect((new AddressGuard)->isPublicHost('client.example.com'))->toBeFalse();
})->with([
    'unspecified' => '0.0.0.0',
    'rfc1918 10/8' => '10.0.0.5',
    'rfc1918 172.16/12' => '172.16.0.1',
    'rfc1918 192.168/16' => '192.168.1.1',
    'cloud metadata' => '169.254.169.254',
    'loopback' => '127.0.0.1',
    'carrier grade nat' => '100.64.0.1',
    'ipv4 multicast' => '224.0.0.1',
    'ipv6 unspecified' => '::',
    'ipv6 loopback' => '::1',
    'ipv6 link local' => 'fe80::1',
    'ipv6 unique local' => 'fc00::1',
    'ipv4 mapped ipv6' => '::ffff:10.0.0.1',
    'ipv6 multicast' => 'ff02::1',
    'nat64' => '64:ff9b::a00:1',
    'local use nat64' => '64:ff9b:1::a9fe:a9fe',
    '6to4' => '2002:7f00:1::',
    'teredo' => '2001:0:4136:e378:8000:63bf:3fff:fdd2',
    'benchmarking' => '198.18.0.1',
    'ietf protocol assignments' => '192.0.0.1',
    'documentation' => '192.0.2.1',
]);

it('rejects ip literals that are not public', function (string $host): void {
    expect((new AddressGuard)->isPublicHost($host))->toBeFalse();
})->with([
    'ipv4 loopback' => '127.0.0.1',
    'ipv6 loopback in brackets' => '[::1]',
    'rfc1918' => '10.1.2.3',
    'cloud metadata' => '169.254.169.254',
]);

it('accepts public ip literals', function (): void {
    expect((new AddressGuard)->isPublicHost('93.184.216.34'))->toBeTrue()
        ->and((new AddressGuard)->isPublicHost('[2606:4700::1111]'))->toBeTrue();
});

it('rejects unresolvable and empty hosts', function (): void {
    AddressGuard::resolveHostsUsing(fn (string $host): array => []);

    expect((new AddressGuard)->isPublicHost('nowhere.example.com'))->toBeFalse()
        ->and((new AddressGuard)->isPublicHost(''))->toBeFalse();
});

it('only accepts loopback addresses when allowed', function (): void {
    AddressGuard::resolveHostsUsing(fn (string $host): array => ['127.0.0.1', '::1']);

    expect((new AddressGuard)->isPublicHost('localhost'))->toBeFalse()
        ->and((new AddressGuard)->isPublicHost('localhost', allowLoopback: true))->toBeTrue()
        ->and((new AddressGuard)->isPublicHost('127.0.0.2', allowLoopback: true))->toBeTrue();
});

it('does not treat private addresses as loopback', function (): void {
    AddressGuard::resolveHostsUsing(fn (string $host): array => ['127.0.0.1', '10.0.0.1']);

    expect((new AddressGuard)->isPublicHost('mixed.example.com', allowLoopback: true))->toBeFalse()
        ->and((new AddressGuard)->isLoopbackAddress('not-an-ip'))->toBeFalse();
});

it('looks up each host once', function (): void {
    $lookups = 0;

    AddressGuard::resolveHostsUsing(function (string $host) use (&$lookups): array {
        $lookups++;

        return ['93.184.216.34'];
    });

    $guard = new AddressGuard;

    $guard->isPublicHost('client.example.com');
    $guard->isPublicHost('client.example.com');

    expect($lookups)->toBe(1);
});

it('pins requests to the addresses that were checked', function (): void {
    AddressGuard::resolveHostsUsing(fn (string $host): array => ['93.184.216.34', '2606:4700::1111']);

    $pins = [];

    Http::fake(function (Request $request, array $options) use (&$pins) {
        $pins[] = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response();
    });

    $guard = new AddressGuard;
    $guard->isPublicHost('client.example.com');

    $guard->pin(Http::withOptions([]))->get('https://client.example.com:8443/metadata.json');
    $guard->pin(Http::withOptions([]))->get('https://unchecked.example.com/metadata.json');

    expect($pins)->toBe([
        ['client.example.com:8443:93.184.216.34,[2606:4700::1111]'],
        null,
    ]);
});

it('is shared with the authorization server discovery host resolver', function (): void {
    AuthServerDiscovery::resolveHostsUsing(fn (string $host): array => ['10.0.0.1']);

    expect((new AddressGuard)->isPublicHost('client.example.com'))->toBeFalse();
});
