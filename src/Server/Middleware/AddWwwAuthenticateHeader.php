<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class AddWwwAuthenticateHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (\Illuminate\Http\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $route = $request->route();
        if (! $route instanceof Route || ! in_array(self::class, app('router')->gatherRouteMiddleware($route), true)) {
            return $response;
        }

        $metadataRoute = app('router')->getRoutes()->getByName('mcp.oauth.protected-resource.nested');
        if ($metadataRoute instanceof Route) {
            $response->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.$this->resourceMetadataUrl($request, $metadataRoute).'"'
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

    protected function resourceMetadataUrl(Request $request, Route $metadataRoute): string
    {
        return route('mcp.oauth.protected-resource.nested', [
            ...(clone $metadataRoute)->bind($request)->parameters(),
            'path' => $request->path(),
        ]);
    }
}
