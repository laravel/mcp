<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

class RequestHeaders
{
    public const PROTOCOL_VERSION = 'MCP-Protocol-Version';

    public const METHOD = 'Mcp-Method';

    public const NAME = 'Mcp-Name';

    public const NAMED_METHODS = ['tools/call', 'prompts/get', 'resources/read'];

    protected const SENTINEL_PREFIX = '=?base64?';

    protected const SENTINEL_SUFFIX = '?=';

    public static function requiresName(string $method): bool
    {
        return in_array($method, self::NAMED_METHODS, true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function name(string $method, array $params): ?string
    {
        $value = match ($method) {
            'tools/call', 'prompts/get' => $params['name'] ?? null,
            'resources/read' => $params['uri'] ?? null,
            default => null,
        };

        return is_string($value) ? $value : null;
    }

    public static function encode(string $value): string
    {
        if (self::isSafe($value)) {
            return $value;
        }

        return self::SENTINEL_PREFIX.base64_encode($value).self::SENTINEL_SUFFIX;
    }

    public static function decode(string $value): string
    {
        if (! str_starts_with($value, self::SENTINEL_PREFIX) || ! str_ends_with($value, self::SENTINEL_SUFFIX)) {
            return $value;
        }

        $decoded = base64_decode(substr(
            $value,
            strlen(self::SENTINEL_PREFIX),
            -strlen(self::SENTINEL_SUFFIX),
        ), true);

        return $decoded === false ? $value : $decoded;
    }

    protected static function isSafe(string $value): bool
    {
        if (str_starts_with($value, self::SENTINEL_PREFIX) && str_ends_with($value, self::SENTINEL_SUFFIX)) {
            return false;
        }

        return preg_match('/^[\x21-\x7E]([\x20-\x7E\x09]*[\x21-\x7E])?\z/', $value) === 1;
    }
}
