<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\ErrorCode;
use Symfony\Component\HttpFoundation\Response;

class ValidateOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null || $this->allowed($origin)) {
            return $next($request);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => ErrorCode::INVALID_REQUEST->value,
                'message' => 'Forbidden: The request origin is not allowed.',
            ],
        ], 403);
    }

    protected function allowed(string $origin): bool
    {
        $origin = $this->normalize($origin);

        if ($origin === null) {
            return false;
        }

        if (in_array(parse_url($origin, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true)) {
            return true;
        }

        $configured = array_filter(Arr::wrap(config('mcp.allowed_origins', [])), is_string(...));

        if (in_array('*', $configured, true)) {
            return true;
        }

        $allowed = array_map($this->normalize(...), [(string) config('app.url'), ...$configured]);

        return in_array($origin, $allowed, true);
    }

    protected function normalize(string $origin): ?string
    {
        $parts = parse_url(strtolower(trim($origin)));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $port = $parts['port'] ?? null;

        if ($port === ($parts['scheme'] === 'https' ? 443 : 80)) {
            $port = null;
        }

        return $parts['scheme'].'://'.$parts['host'].($port === null ? '' : ':'.$port);
    }
}
