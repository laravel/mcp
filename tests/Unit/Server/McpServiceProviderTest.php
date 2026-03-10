<?php

declare(strict_types=1);

use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\Passport;

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

    Passport::$scopes = ['custom' => 'Custom scope'];

    $provider = new McpServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $this->app->boot();

    expect(Passport::$scopes)->toHaveKey('mcp:use');
    expect(Passport::$scopes['custom'])->toBe('Custom scope');
});
