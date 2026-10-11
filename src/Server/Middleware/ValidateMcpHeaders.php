<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;
use Laravel\Mcp\Transport\HeaderValue;
use Laravel\Mcp\Transport\JsonRpcRequest;
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

        $message = new JsonRpcRequest(
            id: is_int($body['id']) || is_string($body['id']) ? $body['id'] : 0,
            method: $body['method'],
            params: is_array($body['params'] ?? null) ? $body['params'] : [],
        );

        if ($message->isLegacy()) {
            return $this->unsupportedLegacyVersion($request, $body['id']) ?? $next($request);
        }

        $mismatch = $this->mismatch($request, RequestHeader::PROTOCOL_VERSION, $message->meta()[MetaKey::PROTOCOL_VERSION->value] ?? null, true)
            ?? $this->mismatch($request, RequestHeader::METHOD, $message->method, true)
            ?? $this->mismatch($request, RequestHeader::NAME, $message->name(), $message->requiresName());

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
     * Legacy clients never send the method and name headers, but they do send
     * the negotiated protocol version after the handshake.
     */
    protected function unsupportedLegacyVersion(Request $request, mixed $id): ?Response
    {
        $version = $request->header(RequestHeader::PROTOCOL_VERSION->value);

        if (! is_string($version) || $version === '' || in_array($version, ProtocolVersion::initializeSupported(), true)) {
            return null;
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value,
                'message' => 'Unsupported protocol version',
                'data' => [
                    'supported' => ProtocolVersion::initializeSupported(),
                    'requested' => $version,
                ],
            ],
        ], 400);
    }

    protected function mismatch(Request $request, RequestHeader $header, mixed $expected, bool $required): ?string
    {
        $value = $request->header($header->value);

        if (! is_string($value) || $value === '') {
            return $required ? "Header mismatch: The [{$header->value}] header is required." : null;
        }

        $headerValue = $header === RequestHeader::NAME
            ? HeaderValue::fromHeader($value)
            : new HeaderValue($value);

        if (! is_string($expected) || $headerValue->matches($expected)) {
            return null;
        }

        return "Header mismatch: The [{$header->value}] header value [{$headerValue->value}] does not match the request body value [{$expected}].";
    }
}
