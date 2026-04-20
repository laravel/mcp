<?php

use Illuminate\Http\Client\Factory;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Exceptions\ClientException;

it('parses resource metadata url from www-authenticate header', function (): void {
    $discovery = new AuthServerDiscovery;

    $url = $discovery->parseResourceMetadataUrl('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');

    expect($url)->toBe('https://example.com/.well-known/oauth-protected-resource');
});

it('throws when www-authenticate header has no resource metadata', function (): void {
    $discovery = new AuthServerDiscovery;

    $discovery->parseResourceMetadataUrl('Bearer realm="example"');
})->throws(ClientException::class, 'WWW-Authenticate header does not contain resource_metadata URL.');

it('discovers auth server endpoints', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource' => $http->response(json_encode([
            'resource' => 'https://example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ])),
        'auth.example.com/.well-known/oauth-authorization-server' => $http->response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
        ])),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $metadata = $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');

    expect($metadata)->toBe([
        'token_endpoint' => 'https://auth.example.com/token',
        'authorization_endpoint' => 'https://auth.example.com/authorize',
    ]);
});

it('discovers endpoints without authorization endpoint', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource' => $http->response(json_encode([
            'resource' => 'https://example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ])),
        'auth.example.com/.well-known/oauth-authorization-server' => $http->response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
        ])),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $metadata = $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');

    expect($metadata)->toBe([
        'token_endpoint' => 'https://auth.example.com/token',
    ]);
});

it('discovers endpoints when auth server url has a path', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource/mcp' => $http->response(json_encode([
            'resource' => 'https://example.com/mcp',
            'authorization_servers' => ['https://example.com/mcp'],
        ])),
        'example.com/.well-known/oauth-authorization-server/mcp' => $http->response(json_encode([
            'issuer' => 'https://example.com/mcp',
            'token_endpoint' => 'https://example.com/oauth/token',
            'authorization_endpoint' => 'https://example.com/oauth/authorize',
        ])),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $metadata = $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource/mcp"');

    expect($metadata)->toBe([
        'token_endpoint' => 'https://example.com/oauth/token',
        'authorization_endpoint' => 'https://example.com/oauth/authorize',
    ]);
});

it('throws when prm fetch fails', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource' => $http->response('Not Found', 404),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');
})->throws(ClientException::class, 'Failed to fetch Protected Resource Metadata');

it('throws when prm has no authorization servers', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource' => $http->response(json_encode([
            'resource' => 'https://example.com/mcp',
        ])),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');
})->throws(ClientException::class, 'Protected Resource Metadata does not contain authorization_servers.');

it('throws when auth server metadata fetch fails', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource' => $http->response(json_encode([
            'resource' => 'https://example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ])),
        'auth.example.com/.well-known/oauth-authorization-server' => $http->response('Not Found', 404),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');
})->throws(ClientException::class, 'Failed to fetch OAuth Authorization Server Metadata');

it('throws when auth server metadata has no token endpoint', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/.well-known/oauth-protected-resource' => $http->response(json_encode([
            'resource' => 'https://example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ])),
        'auth.example.com/.well-known/oauth-authorization-server' => $http->response(json_encode([
            'issuer' => 'https://auth.example.com',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
        ])),
    ]);

    $discovery = new AuthServerDiscovery($http);

    $discovery->discover('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');
})->throws(ClientException::class, 'OAuth Authorization Server Metadata does not contain token_endpoint.');
