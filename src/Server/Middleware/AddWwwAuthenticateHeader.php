<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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

        $url = $this->resourceMetadataUrl($request);

        $response->header('WWW-Authenticate', $url === null
            ? 'Bearer realm="mcp", error="invalid_token"'
            : 'Bearer realm="mcp", resource_metadata="'.$url.'"');

        return $response;
    }

    protected function resourceMetadataUrl(Request $request): ?string
    {
        if (static::$resourceMetadataUrlResolver instanceof Closure) {
            $url = (static::$resourceMetadataUrlResolver)($request);

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $metadata = app('router')->getRoutes()->getByName('mcp.oauth.protected-resource.nested');

        if (! $metadata instanceof Route) {
            return null;
        }

        return route('mcp.oauth.protected-resource.nested', [
            ...(clone $metadata)->bind($request)->parameters(),
            'path' => $request->path(),
        ]);
    }
}
