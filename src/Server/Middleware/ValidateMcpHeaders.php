<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Support\RequestHeaders;
use Symfony\Component\HttpFoundation\Response;

class ValidateMcpHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $body = json_decode((string) $request->getContent(), true);

        if (! is_array($body) || ! isset($body['id']) || ! is_string($body['method'] ?? null)) {
            return $next($request);
        }

        $method = $body['method'];

        if ($method === 'initialize') {
            return $next($request);
        }

        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];

        $mismatch = $this->mismatch($request, RequestHeaders::PROTOCOL_VERSION, $meta[MetaKey::PROTOCOL_VERSION->value] ?? null, true)
            ?? $this->mismatch($request, RequestHeaders::METHOD, $method, true)
            ?? $this->mismatch($request, RequestHeaders::NAME, RequestHeaders::name($method, $params), RequestHeaders::requiresName($method));

        if ($mismatch === null) {
            return $next($request);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $body['id'],
            'error' => [
                'code' => ErrorCode::HEADER_MISMATCH->value,
                'message' => $mismatch,
            ],
        ], 400);
    }

    protected function mismatch(Request $request, string $header, mixed $expected, bool $required): ?string
    {
        $value = $request->header($header);

        if (! is_string($value) || $value === '') {
            return $required ? "Header mismatch: The [{$header}] header is required." : null;
        }

        if ($header === RequestHeaders::NAME) {
            $value = RequestHeaders::decode($value);
        }

        if (is_string($expected) && $value !== $expected) {
            return "Header mismatch: The [{$header}] header value [{$value}] does not match the request body value [{$expected}].";
        }

        return null;
    }
}
