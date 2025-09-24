<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttemptAuthenticate
{
    public function __construct(
        protected Authenticate $authenticate,
    ) {
        //
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        try {
            $this->authenticate->handle(
                $request,
                fn ($request) => $request,
                ...$guards
            );
        } catch (AuthenticationException) {
            //
        }

        return $next($request);
    }
}
