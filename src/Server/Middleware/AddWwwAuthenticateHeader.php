<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddWwwAuthenticateHeader
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Illuminate\Http\Response $response */
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

        $response->header(
            'WWW-Authenticate',
            'Bearer realm="mcp", error="invalid_token"'
        );

        return $response;
    }
}
