<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class AddWwwAuthenticateHeader
{
    protected static ?Closure $resourceMetadataUrlResolver = null;

    public static function resolveResourceMetadataUrlUsing(?Closure $callback): void
    {
        static::$resourceMetadataUrlResolver = $callback;
    }

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

        $url = $this->resourceMetadataUrl($request, $route);

        $response->header('WWW-Authenticate', $url === null
            ? 'Bearer realm="mcp", error="invalid_token"'
            : 'Bearer realm="mcp", resource_metadata="'.$url.'"');

        return $response;
    }

    protected function resourceMetadataUrl(Request $request, Route $route): ?string
    {
        if (static::$resourceMetadataUrlResolver instanceof Closure) {
            $url = (static::$resourceMetadataUrlResolver)($request);

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        if (! app('router')->has('mcp.oauth.protected-resource.nested')) {
            return null;
        }

        return route('mcp.oauth.protected-resource.nested', [
            ...Arr::only($route->originalParameters(), $route->getCompiled()?->getHostVariables() ?? []),
            'path' => $request->path(),
        ]);
    }
}
