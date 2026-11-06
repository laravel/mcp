<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\OAuth;

use Illuminate\Support\Str;

class RedirectUriPolicy
{
    /**
     * @param  array<int, string>  $redirectDomains
     * @param  array<int, string>  $customSchemes
     */
    public function __construct(
        protected array $redirectDomains = [],
        protected array $customSchemes = [],
    ) {
        //
    }

    /**
     * Create a policy from the "mcp.redirect_domains" and "mcp.custom_schemes" configuration.
     */
    public static function fromConfig(): static
    {
        /** @var array<int, string> $redirectDomains */
        $redirectDomains = config('mcp.redirect_domains', []);

        /** @var array<int, string> $customSchemes */
        $customSchemes = config('mcp.custom_schemes', []);

        return new static($redirectDomains, $customSchemes);
    }

    /**
     * Determine if the redirect URI is well formed and uses an allowed scheme.
     */
    public function isValid(string $uri): bool
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        if (parse_url($uri, PHP_URL_USER) !== null || parse_url($uri, PHP_URL_PASS) !== null) {
            return false;
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            return Str::isUrl($uri, ['http', 'https']);
        }

        $host = parse_url($uri, PHP_URL_HOST);

        return in_array($scheme, $this->customSchemes, true) && is_string($host) && $host !== '';
    }

    /**
     * Determine if a valid redirect URI points at a permitted redirect domain.
     */
    public function isPermitted(string $uri): bool
    {
        if (! in_array(parse_url($uri, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return true;
        }

        if (in_array('*', $this->redirectDomains, true)) {
            return true;
        }

        if ($this->hasLocalhostDomain() && $this->isLocalhost($uri)) {
            return true;
        }

        return Str::startsWith($uri, $this->allowedDomains());
    }

    /**
     * Determine if the redirect URI is a plain HTTP loopback URI.
     */
    public function isLocalhost(string $uri): bool
    {
        $parts = parse_url($uri);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'http' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return in_array($parts['host'] ?? null, ['localhost', '127.0.0.1', '[::1]'], true);
    }

    /**
     * Get the allowed redirect domains, each ending with a slash.
     *
     * @return array<int, string>
     */
    public function allowedDomains(): array
    {
        return collect($this->redirectDomains)
            ->map(fn (string $domain): string => Str::endsWith($domain, '/')
                ? $domain
                : "{$domain}/"
            )
            ->all();
    }

    protected function hasLocalhostDomain(): bool
    {
        return collect($this->redirectDomains)->contains(fn (string $domain): bool => in_array(
            rtrim(Str::after($domain, '://'), '/'),
            ['localhost', '127.0.0.1', '[::1]'],
            true,
        ));
    }
}
