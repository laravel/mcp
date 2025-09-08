<?php

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddWwwAuthenticateHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 401) {
            $response->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.route('mcp.oauth.protected-resource').'"'
            );
        }

        return $response;
    }
}
