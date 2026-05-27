<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;

interface TokenStore
{
    public function get(string $key): ?TokenSet;

    public function put(string $key, TokenSet $set): void;

    public function forget(string $key): void;

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function lock(string $key, Closure $work): mixed;
}
