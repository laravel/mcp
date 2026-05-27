<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;

class InMemoryTokenStore implements TokenStore
{
    /** @var array<string, TokenSet> */
    protected array $tokens = [];

    public function get(string $key): ?TokenSet
    {
        return $this->tokens[$key] ?? null;
    }

    public function put(string $key, TokenSet $set): void
    {
        $this->tokens[$key] = $set;
    }

    public function forget(string $key): void
    {
        unset($this->tokens[$key]);
    }

    public function lock(string $key, Closure $work): mixed
    {
        return $work();
    }
}
