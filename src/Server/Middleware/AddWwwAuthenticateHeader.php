<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddWwwAuthenticateHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $isOauth = app('router')->has('mcp.oauth.protected-resource');
        if ($isOauth) {
            $response->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.route('mcp.oauth.protected-resource', ['path' => $request->path()]).'"'
            );

            return $response;
        }

        // Sanctum, can't share discover URL
        $response->header(
            'WWW-Authenticate',
            'Bearer realm="mcp", error="invalid_token"'
        );

        return $response;
    }
}
