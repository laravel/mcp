<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReorderJsonAccept
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accept = $request->header('Accept');
        if (is_string($accept) && str_contains($accept, ',')) {
            $accept = array_map(trim(...), explode(',', $accept));
        }

        if (! is_array($accept)) {
            return $next($request);
        }

        usort($accept, fn ($a, $b): int => str_contains((string) $b, 'application/json') <=> str_contains((string) $a, 'application/json'));
        $request->headers->set('Accept', implode(', ', $accept));

        return $next($request);
    }
}
