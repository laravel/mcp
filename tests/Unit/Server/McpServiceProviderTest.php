<?php

declare(strict_types=1);

use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\Passport;

it('registers mcp scope during boot', function (): void {
    if (! class_exists(Passport::class)) {
        require_once __DIR__.'/../../Fixtures/PassportPassport.php';
    }

    Passport::$scopes = ['custom' => 'Custom scope'];

    $provider = new McpServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $this->app->boot();

    expect(Passport::$scopes)->toHaveKey('mcp:use');
    expect(Passport::$scopes['custom'])->toBe('Custom scope');
});
