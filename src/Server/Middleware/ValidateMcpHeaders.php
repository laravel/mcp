<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if (! is_array($body) || ! isset($body['id']) || ! is_string($body['method'] ?? null)) {
            return $next($request);
        }

        $method = $body['method'];

        if ($method === 'initialize') {
            return $next($request);
        }

        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];

        $mismatch = $this->mismatch($request, 'MCP-Protocol-Version', $meta[MetaKey::PROTOCOL_VERSION->value] ?? null)
            ?? $this->mismatch($request, 'Mcp-Method', $method)
            ?? $this->mismatch($request, 'Mcp-Name', $this->name($method, $params));

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

    /**
     * @param  array<string, mixed>  $params
     */
    protected function name(string $method, array $params): ?string
    {
        $value = match ($method) {
            'tools/call', 'prompts/get' => $params['name'] ?? null,
            'resources/read' => $params['uri'] ?? null,
            default => null,
        };

        return is_string($value) ? $value : null;
    }

    protected function mismatch(Request $request, string $header, mixed $expected): ?string
    {
        if (! is_string($expected)) {
            return null;
        }

        $value = $request->header($header);

        if (! is_string($value) || $value === '') {
            return "Header mismatch: The [{$header}] header is required.";
        }

        $value = $this->decode($value);

        if ($value !== $expected) {
            return "Header mismatch: The [{$header}] header value [{$value}] does not match the request body value [{$expected}].";
        }

        return null;
    }

    protected function decode(string $value): string
    {
        if (! str_starts_with($value, '=?base64?') || ! str_ends_with($value, '?=')) {
            return $value;
        }

        $decoded = base64_decode(substr($value, 9, -2), true);

        return $decoded === false ? $value : $decoded;
    }
}
