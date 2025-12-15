<?php

declare(strict_types=1);

use Laravel\Mcp\Server\McpServiceProvider;

it('registers mcp scope during boot', function (): void {
    if (! class_exists('Laravel\Passport\Passport')) {
        eval('
            namespace Laravel\Passport;
            class Passport {
                public static $scopes = [];
                public static function tokensCan($scopes) {
                    self::$scopes = $scopes;
                }
            }
        ');
    }

    \Laravel\Passport\Passport::$scopes = ['custom' => 'Custom scope'];

    $provider = new McpServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect(\Laravel\Passport\Passport::$scopes)->toHaveKey('mcp:use');
    expect(\Laravel\Passport\Passport::$scopes['custom'])->toBe('Custom scope');
});
