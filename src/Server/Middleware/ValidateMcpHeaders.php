<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\MetaKey;
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

        if ($body['method'] === 'initialize') {
            return $next($request);
        }

        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];

        $message = new JsonRpcRequest(
            id: is_int($body['id']) || is_string($body['id']) ? $body['id'] : 0,
            method: $body['method'],
            params: $params,
        );

        $mismatch = $this->mismatch($request, RequestHeader::PROTOCOL_VERSION, $meta[MetaKey::PROTOCOL_VERSION->value] ?? null, true)
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
