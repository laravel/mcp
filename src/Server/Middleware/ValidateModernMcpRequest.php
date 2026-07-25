<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the request metadata headers the 2026-07-28 Streamable HTTP
 * revision requires: MCP-Protocol-Version, Mcp-Method, and Mcp-Name must
 * mirror the corresponding JSON-RPC body fields.
 */
class ValidateModernMcpRequest
{
    /**
     * Methods that must mirror a body field into the Mcp-Name header,
     * mapped to the parameter carrying the name.
     */
    protected const NAMED_METHODS = [
        'tools/call' => 'name',
        'prompts/get' => 'name',
        'resources/read' => 'uri',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $body = json_decode($request->getContent(), true);

        if (! is_array($body)) {
            return $next($request);
        }

        $meta = $body['params']['_meta'] ?? null;
        $declaredVersion = is_array($meta) ? ($meta[MetaKey::PROTOCOL_VERSION->value] ?? null) : null;
        $declaredVersion = is_string($declaredVersion) ? $declaredVersion : null;

        $headerVersion = $request->headers->get('MCP-Protocol-Version');

        $modern = ($declaredVersion !== null && ProtocolVersion::isModern($declaredVersion))
            || ($headerVersion !== null && ProtocolVersion::isModern($headerVersion));

        // Notifications have no defined header requirements in this revision, and
        // legacy-era requests are validated by their own revision's rules.
        if (! $modern || ! array_key_exists('id', $body)) {
            return $next($request);
        }

        $requestId = is_string($body['id']) || is_int($body['id']) ? $body['id'] : null;

        if ($headerVersion !== $declaredVersion) {
            return $this->headerMismatch($requestId, sprintf(
                'Header mismatch: MCP-Protocol-Version header value %s does not match body value %s',
                $this->describe($headerVersion),
                $this->describe($declaredVersion),
            ));
        }

        $method = $body['method'] ?? null;
        $headerMethod = $request->headers->get('Mcp-Method');

        if (! is_string($method) || $headerMethod !== $method) {
            return $this->headerMismatch($requestId, sprintf(
                'Header mismatch: Mcp-Method header value %s does not match body value %s',
                $this->describe($headerMethod),
                $this->describe(is_string($method) ? $method : null),
            ));
        }

        $nameParameter = self::NAMED_METHODS[$method] ?? null;

        if ($nameParameter !== null) {
            $bodyName = $body['params'][$nameParameter] ?? null;
            $headerName = $this->decodeHeaderValue($request->headers->get('Mcp-Name'));

            if (! is_string($bodyName) || $headerName !== $bodyName) {
                return $this->headerMismatch($requestId, sprintf(
                    'Header mismatch: Mcp-Name header value %s does not match body value %s',
                    $this->describe($headerName),
                    $this->describe(is_string($bodyName) ? $bodyName : null),
                ));
            }
        }

        return $next($request);
    }

    /**
     * Decode the Base64 sentinel format ("=?base64?{value}?=") when present.
     */
    protected function decodeHeaderValue(?string $value): ?string
    {
        if ($value === null || preg_match('/^=\?base64\?(.*)\?=$/s', $value, $matches) !== 1) {
            return $value;
        }

        $decoded = base64_decode($matches[1], true);

        return $decoded === false ? null : $decoded;
    }

    protected function headerMismatch(string|int|null $requestId, string $message): JsonResponse
    {
        return new JsonResponse(
            JsonRpcResponse::error($requestId, ErrorCode::HEADER_MISMATCH->value, $message)->toArray(),
            400,
        );
    }

    protected function describe(?string $value): string
    {
        return $value === null ? '[missing]' : "'{$value}'";
    }
}
