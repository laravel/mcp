<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
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

        if (! is_array($body) || ! is_string($body['method'] ?? null)) {
            return $next($request);
        }

        $method = $body['method'];

        if ($method === 'initialize') {
            return $next($request);
        }

        $params = Arr::wrap($body['params'] ?? null);
        $meta = Arr::wrap($params['_meta'] ?? null);

        $expected = [
            'MCP-Protocol-Version' => $meta[MetaKey::PROTOCOL_VERSION->value] ?? null,
            'Mcp-Method' => $method,
        ];

        $target = $params['name'] ?? $params['uri'] ?? null;

        if ($target !== null) {
            $expected['Mcp-Name'] = $target;
        }

        foreach ($expected as $header => $value) {
            $mismatch = $this->mismatch($request, $header, $value);

            if ($mismatch !== null) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $body['id'] ?? null,
                    'error' => [
                        'code' => ErrorCode::HEADER_MISMATCH->value,
                        'message' => $mismatch,
                    ],
                ], 400);
            }
        }

        return $next($request);
    }

    protected function mismatch(Request $request, string $header, mixed $expected): ?string
    {
        $value = $request->header($header);

        if (! is_string($value) || $value === '') {
            return "Header mismatch: The [{$header}] header is required.";
        }

        if ($header === 'Mcp-Name') {
            $value = $this->decode($value);
        }

        if (is_string($expected) && $value !== $expected) {
            return "Header mismatch: The [{$header}] header value [{$value}] does not match the request body value [{$expected}].";
        }

        return null;
    }

    protected function decode(string $value): string
    {
        $encoded = Str::match('/^=\?base64\?(.+)\?=$/s', $value);

        if ($encoded === '') {
            return $value;
        }

        $decoded = base64_decode($encoded, true);

        return $decoded === false ? $value : $decoded;
    }
}
